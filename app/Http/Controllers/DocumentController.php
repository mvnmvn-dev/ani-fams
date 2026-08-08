<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    public function index(DocumentService $service)
    {
        $q = trim((string) request()->query('q', ''));
        $contentHits = $q !== '' ? $service->search($q) : [];

        return view('documents.index', [
            'pageTitle' => 'Document Management',
            'documents' => $service->listDocuments(),
            'categories' => $service->categories(),
            'contentQuery' => $q,
            'contentHits' => $contentHits,
            'canUpload' => Auth::user()->canPermission('document.upload'),
            'canManage' => Auth::user()->canPermission('document.manage'),
            'openRecordId' => request()->query('open'),
        ]);
    }

    public function store(Request $request, DocumentService $service)
    {
        $file = $request->file('document_file');
        $fileInput = $file ? [
            'name' => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
            'error' => UPLOAD_ERR_OK,
            'size' => $file->getSize(),
        ] : ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'tmp_name' => ''];

        $result = $service->uploadDocument($request->all(), $fileInput);

        return redirect()->route('documents.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Document uploaded successfully.' : $result['error']
        );
    }

    public function show(Request $request, string $id, DocumentService $service)
    {
        $doc = $service->documentDetails((int) $id);
        if (! $doc) {
            return redirect()->route('documents.index')->with('flash_error', 'Document not found.');
        }

        $roles = DB::table('roles')->select('id', 'name')->orderBy('name')->get()->map(fn ($r) => (array) $r)->all();

        $data = [
            'pageTitle' => $doc['title'] ?? 'Document',
            'doc' => $doc,
            'roles' => $roles,
            'canManage' => Auth::user()->canPermission('document.manage') || (int) $doc['owner_id'] === (int) Auth::id(),
            'canUpload' => Auth::user()->canPermission('document.upload'),
            'asModal' => true,
        ];

        if ($request->boolean('modal') || $request->ajax()) {
            return response()->view('documents.modal', $data);
        }

        return redirect()->route('documents.index', ['open' => $id]);
    }

    public function preview(string $id, DocumentService $service)
    {
        $result = $service->preview((int) $id);
        if (! ($result['success'] ?? false)) {
            return redirect()->route('documents.index')->with('flash_error', $result['error'] ?? 'Unable to preview.');
        }

        if (($result['mode'] ?? '') === 'file') {
            return response()->file($result['path'], [
                'Content-Type' => $result['mime'] ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . ($result['filename'] ?? 'document') . '"',
            ]);
        }

        return view('documents.preview', [
            'pageTitle' => 'Preview: ' . ($result['title'] ?? 'Document'),
            'doc' => $result['doc'],
            'content' => $result['content'] ?? null,
            'mode' => $result['mode'],
            'downloadUrl' => $result['download_url'] ?? null,
            'mime' => $result['mime'] ?? null,
        ]);
    }

    public function compare(Request $request, string $id, DocumentService $service)
    {
        $v1 = (int) $request->query('v1', 0);
        $v2 = (int) $request->query('v2', 0);
        $result = $service->compareVersions((int) $id, $v1, $v2);
        if (! ($result['success'] ?? false)) {
            return redirect()->route('documents.index', ['open' => $id])->with('flash_error', $result['error'] ?? 'Unable to compare.');
        }

        return view('documents.compare', [
            'pageTitle' => 'Compare versions',
            'doc' => $result['doc'],
            'left' => $result['left'],
            'right' => $result['right'],
            'diff' => $result['diff'] ?? null,
            'metaDiff' => $result['meta_diff'] ?? [],
            'textComparable' => $result['text_comparable'] ?? false,
        ]);
    }

    public function addVersion(Request $request, string $id, DocumentService $service)
    {
        $file = $request->file('document_file');
        $fileInput = $file ? [
            'name' => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
            'error' => UPLOAD_ERR_OK,
            'size' => $file->getSize(),
        ] : ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'tmp_name' => ''];

        $result = $service->uploadNewVersion((int) $id, $request->all(), $fileInput);

        return redirect()->route('documents.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'New version uploaded.' : $result['error']
        );
    }

    public function updateAccess(Request $request, string $id, DocumentService $service)
    {
        $result = $service->manageAccess((int) $id, $request->all());

        return redirect()->route('documents.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Document access updated.' : $result['error']
        );
    }

    public function destroy(string $id, DocumentService $service)
    {
        $result = $service->softDelete((int) $id);

        return redirect()->route('documents.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Document moved to trash.' : ($result['error'] ?? 'Unable to delete.')
        );
    }

    public function exportCsv(DocumentService $service)
    {
        $documents = $service->listDocuments();
        $headers = ['ID', 'Document Number', 'Title', 'Description', 'Category', 'Owner Name', 'Confidentiality', 'Status', 'Current Version', 'Created At'];
        $rows = [];
        foreach ($documents as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['document_number'] ?? '',
                $row['title'] ?? '',
                $row['description'] ?? '',
                $row['category_name'] ?? '',
                $row['owner_name'] ?? '',
                $row['confidentiality'] ?? '',
                $row['status'] ?? '',
                $row['version_no'] ?? '',
                $row['created_at'] ?? '',
            ];
        }

        return $this->streamExportCsv('documents', $headers, $rows);
    }

    public function exportPdf(DocumentService $service)
    {
        $documents = $service->listDocuments();
        $headers = ['ID', 'Number', 'Title', 'Category', 'Owner', 'Confidentiality', 'Status', 'Version'];
        $rows = [];
        foreach ($documents as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['document_number'] ?? '',
                $row['title'] ?? '',
                $row['category_name'] ?? '',
                $row['owner_name'] ?? '',
                $row['confidentiality'] ?? '',
                $row['status'] ?? '',
                $row['version_no'] ?? '',
            ];
        }

        return $this->streamExportPdf('documents', 'Documents Export', $headers, $rows);
    }
}
