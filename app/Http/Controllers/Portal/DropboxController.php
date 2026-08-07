<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\DropboxService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DropboxController extends Controller
{
    protected DropboxService $dropbox;

    public function __construct(DropboxService $dropbox)
    {
        $this->dropbox = $dropbox;
    }

    /**
     * Display the embedded Dropbox explorer in the portal
     */
    public function index(Request $request): View
    {
        $currentPath = $request->query('path', '');
        $files = [];
        $isConnected = $this->dropbox->testConnection();
        
        if ($isConnected) {
            $files = $this->dropbox->listFolder($currentPath);
        }

        // Generate breadcrumbs for path navigation
        $breadcrumbs = [];
        if (!empty($currentPath)) {
            $parts = explode('/', trim($currentPath, '/'));
            $accumulated = '';
            foreach ($parts as $part) {
                $accumulated .= '/' . $part;
                $breadcrumbs[] = [
                    'name' => $part,
                    'path' => $accumulated
                ];
            }
        }

        return view('portal.dropbox', compact('files', 'currentPath', 'breadcrumbs', 'isConnected'));
    }
}
