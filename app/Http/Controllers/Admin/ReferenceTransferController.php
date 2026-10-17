<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\ReferenceData;
use App\Domain\Administration\TabularFile;
use App\Http\Controllers\Controller;
use App\Models\ReferenceValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReferenceTransferController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.transfer', ['upload' => $request->session()->get('reference_upload'), 'preview' => $request->session()->get('reference_preview')]);
    }

    public function upload(Request $request, TabularFile $files)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'extensions:csv,xlsx']]);
        $upload = $files->read($request->file('file'));
        $request->session()->put('reference_upload', [...$upload, 'actor_id' => $request->user()->id, 'expires_at' => now()->addMinutes(30)->timestamp]);
        $request->session()->forget('reference_preview');

        return to_route('admin.transfer');
    }

    public function preview(Request $request, ReferenceData $service)
    {
        $upload = $this->uploadFromSession($request);
        $request->session()->forget('reference_preview');
        $rules = [];
        foreach (['category', 'code', 'label', 'parent_code', 'is_active'] as $field) {
            $rules['mapping.'.$field] = [in_array($field, ['category', 'code', 'label']) ? 'required' : 'nullable', 'integer', 'min:0', 'max:'.(count($upload['header']) - 1)];
        }
        $mapping = $request->validate($rules)['mapping'];
        $rows = $service->map($upload, $mapping);
        $request->session()->put('reference_preview', $rows);

        return to_route('admin.transfer');
    }

    public function commit(Request $request, ReferenceData $service)
    {
        $this->uploadFromSession($request);
        $rows = $request->session()->get('reference_preview');
        abort_unless(is_array($rows) && count($rows), 422, 'Validate the import before saving.');
        DB::transaction(function () use ($rows, $service) {
            $countries = array_column(array_filter($rows, fn ($row) => $row['category'] === 'country'), 'code');
            foreach ($rows as $row) {
                $service->validate($row);
                $service->validateParent($row, $countries);
                $reference = ReferenceValue::firstOrNew(['category' => $row['category'], 'code' => $row['code']]);
                $old = $reference->exists ? $reference->toArray() : null;
                $reference->fill($row)->save();
                AdminAudit::record('admin.reference.imported', ReferenceValue::class, $reference->id, $old, $reference->toArray());
            }
        });
        $request->session()->forget(['reference_upload', 'reference_preview']);

        return to_route('admin.transfer')->with('success', count($rows).' reference values imported.');
    }

    public function export(Request $request, TabularFile $files)
    {
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx', 'print'])]])['format'];
        $header = ['category', 'code', 'label', 'parent_code', 'is_active'];
        $values = ReferenceValue::orderBy('category')->orderBy('code')->get();
        AdminAudit::record('admin.reference.exported', ReferenceValue::class, 0, null, ['format' => $format, 'count' => $values->count()]);
        if ($format === 'print') {
            return view('admin.print', compact('values'));
        }
        $rows = [$header];
        foreach ($values as $value) {
            $rows[] = [$value->category, $value->code, $value->label, $value->parent_code, (int) $value->is_active];
        }
        if ($format === 'xlsx') {
            return response($files->xlsx($rows), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="reference-values.xlsx"']);
        }

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                $row = array_map(fn ($cell) => preg_match('/^[\s]*[=+@\-]/u', (string) $cell) ? "'".$cell : $cell, $row);
                fputcsv($handle, $row, ',', '"', '');
            }
            fclose($handle);
        }, 'reference-values.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function uploadFromSession(Request $request): array
    {
        $upload = $request->session()->get('reference_upload');
        abort_unless($upload && $upload['actor_id'] === $request->user()->id && $upload['expires_at'] >= now()->timestamp, 422, 'Import expired. Upload the file again.');

        return $upload;
    }
}
