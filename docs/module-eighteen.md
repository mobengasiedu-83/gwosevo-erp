# Module 18: Knowledge Bank & AI Search

Module 18 adds an organization-scoped knowledge bank for controlled articles and documents. Find **Knowledge Bank & AI Search** in the **Knowledge & Intelligence** sidebar group.

## Included

- Shared, department, project, and restricted knowledge spaces. Restricted spaces use per-user membership; department spaces use organization memberships; project spaces use project membership.
- Draft, review, approval, publication, revision, and archive workflow. Authors cannot review or publish their own article; publication requires a separate reviewer and publisher.
- PDF, Word (`.doc` / `.docx`), and text attachments in private storage. Text indexing uses built-in text/Word extraction and the server's `pdftotext` and `antiword` tools for PDF and legacy Word files. An attachment remains downloadable when server extraction tools are unavailable.
- Categories, tags, keyword search, article feedback, and organization-level search analytics.
- Optional grounded AI answers using only published articles in spaces the requesting user can access. Answers link to their source articles.
- Optional semantic retrieval through an OpenAI-compatible embeddings endpoint and Qdrant. Qdrant collections are created when first used with the configured vector dimensions.
- Optional Microsoft 365 SharePoint folder sync using Microsoft Graph application credentials. Imported content is held for local review before publication. Changed items return to review; removed source items are archived after a complete successful sync. A sync reads one folder level, up to 1,000 items.

## Install and grant access

On the local application, run:

```sh
php artisan migrate
php artisan db:seed --class=IdentitySeeder
php artisan config:clear
```

The module is enabled by its migration. The Administrator role receives all knowledge permissions. The Knowledge Manager role is available for assigned staff. Review permissions in **Administration → Permissions & Data Access** before inviting users.

Permissions include `knowledge.search`, `knowledge.spaces.manage`, `knowledge.members.manage`, `knowledge.articles.create`, `knowledge.articles.edit`, `knowledge.articles.review`, `knowledge.articles.publish`, `knowledge.articles.archive`, `knowledge.documents.view`, `knowledge.documents.upload`, `knowledge.ai.ask`, `knowledge.feedback`, `knowledge.analytics.view`, and `knowledge.sync.manage`.

Create a space and select its scope. For restricted spaces, enter user IDs belonging to the active organization. For department or project spaces, enter the matching department or project ID; users must be assigned to that department or project to see its content.

## Optional AI and semantic search

AI and vector indexing are off by default. Set values in the local `.env` only after choosing an approved provider:

```dotenv
KNOWLEDGE_AI_ENABLED=true
KNOWLEDGE_AI_BASE_URL=https://api.openai.com/v1
KNOWLEDGE_AI_API_KEY=
KNOWLEDGE_AI_MODEL=gpt-4o-mini
KNOWLEDGE_AI_TIMEOUT=30
KNOWLEDGE_SEMANTIC_ENABLED=true
KNOWLEDGE_EMBEDDING_MODEL=text-embedding-3-small
KNOWLEDGE_EMBEDDING_DIMENSIONS=1536
KNOWLEDGE_QDRANT_URL=https://your-qdrant-host
KNOWLEDGE_QDRANT_API_KEY=
KNOWLEDGE_QDRANT_COLLECTION=erp_knowledge
```

When AI answers are enabled, the question and retrieved permitted source excerpts are sent to the configured AI endpoint. When semantic indexing is enabled, chunks from published articles are sent to the configured embedding endpoint; Qdrant receives vectors and record IDs, not article text. Confirm organizational approval and provider retention terms first. Keep API keys only in environment configuration, never in the UI or source control. Use HTTPS for provider endpoints.

## Optional SharePoint sync

Set `KNOWLEDGE_GRAPH_TENANT_ID`, `KNOWLEDGE_GRAPH_CLIENT_ID`, and `KNOWLEDGE_GRAPH_CLIENT_SECRET`. Register an Entra application with application-only Microsoft Graph read access, grant administrator consent, and restrict it to the required SharePoint site where possible. Then, as a user with `knowledge.sync.manage`, create a mapping on the Knowledge Bank page using the target space, drive ID, and folder item ID. Click **Sync SharePoint files** to run the import.

Sync-created and changed items are drafts in `pending_review`. An authorized reviewer and a different publisher must approve and publish them. Sync activity and failure messages appear in the page log. Deletion reconciliation happens only after the full folder listing completes; folders over 1,000 items fail without applying removals.

## Privacy and operational limits

- Search and assistant retrieval are organization and space scoped. Only published articles are offered as answer sources.
- Documents are private; the download route checks organization, space membership, and `knowledge.documents.view`.
- Imported SharePoint content is not published automatically.
- There is no recursive folder traversal, multi-drive discovery, delegated user consent flow, or OneDrive/Teams chat sync. Configure a SharePoint folder mapping directly.
- PDF/legacy Word indexing depends on the `pdftotext`/`antiword` binaries being available to the PHP process; the page reports extraction status.
- This module does not scan uploads for malware or classify content automatically. Establish document retention, review, and access policies before production use.
