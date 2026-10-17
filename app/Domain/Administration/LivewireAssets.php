<?php

namespace App\Domain\Administration;

use Illuminate\Support\HtmlString;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;

class LivewireAssets
{
    public function scripts(): HtmlString
    {
        // Livewire 3 defaults to root-relative assets and update URLs. Preserve
        // Laravel's detected base URL when this ERP is served in a subdirectory.
        $uri = app('livewire')->getUpdateUri();
        $script = FrontendAssets::scripts(['url' => url(config('app.debug') ? '/livewire/livewire.js' : '/livewire/livewire.min.js')]);

        return new HtmlString(str_replace('data-update-uri="'.$uri.'"', 'data-update-uri="'.e(url($uri)).'"', $script));
    }
}
