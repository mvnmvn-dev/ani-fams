<?php

namespace App\Http\Controllers;

use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function index(DocumentService $service)
    {
        return view('documents.index', [
            'pageTitle' => 'Document Management',
            'documents' => $service->listDocuments(),
            'categories' => $service->categories(),
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
}
