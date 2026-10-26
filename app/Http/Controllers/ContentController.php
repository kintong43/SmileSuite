<?php

namespace App\Http\Controllers;

use App\Cms\CmsUnavailable;
use App\Cms\DirectusClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function page(DirectusClient $cms, string $slug): View
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,100}$/D', $slug), 404);
        try {
            $page = $cms->published('ss_pages', $slug)[0] ?? null;
            abort_if($page === null, 404);

            return view('content.page', ['page' => $page, 'unavailable' => false]);
        } catch (CmsUnavailable) {
            return view('content.page', ['page' => ['title' => ucwords(str_replace('-', ' ', $slug)), 'body' => 'SmileSuite connects clinic scheduling, patient access, services and clinic-owned storefronts.'], 'unavailable' => true]);
        }
    }

    public function announcements(DirectusClient $cms): View
    {
        try {
            return view('content.announcements', ['announcements' => $cms->published('ss_announcements'), 'services' => $cms->published('ss_services'), 'unavailable' => false]);
        } catch (CmsUnavailable) {
            return view('content.announcements', ['announcements' => [], 'services' => [], 'unavailable' => true]);
        }
    }

    public function dashboard(Request $request, DirectusClient $cms): View
    {
        abort_unless($request->user()?->hasRole('owner'), 403);
        try {
            $counts = [];
            foreach (['ss_pages', 'ss_announcements', 'ss_services', 'ss_directory', 'ss_reports'] as $collection) {
                $counts[$collection] = count($cms->items($collection));
            }
            $available = true;
        } catch (CmsUnavailable) {
            $counts = [];
            $available = false;
        }

        return view('cms', ['available' => $available, 'counts' => $counts, 'adminUrl' => $cms->adminUrl(), 'scope' => $cms->scope()]);
    }
}
