<?php

namespace App\Http\Controllers;

use App\Exports\LogExport;
use App\Support\LogCategories;
use danielme85\LaravelLogToDB\LogToDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class LogController extends Controller
{

    protected array $channels = [
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
        'ALERT',
        'EMERGENCY',
    ];

    public function __construct()
    {
        $this->middleware('permission:view logs');
    }

    public function set_filter($filter)
    {

        if (!in_array($filter, $this->channels) && $filter != 'all') {
            return redirect()->back()->with([
                'type' => 'error',
                'Meldung' => 'Invalid filter',
            ]);
        }

        if ($filter == 'all') {
            $filter = null;
        }


        session(['log_filter' => $filter ? Str::upper($filter) : null]);
        return redirect()->back();

    }

    public function set_category($category)
    {
        if ($category !== 'all' && !LogCategories::exists($category)) {
            return redirect()->back()->with([
                'type' => 'error',
                'Meldung' => 'Ungültige Kategorie',
            ]);
        }

        session(['log_category' => $category === 'all' ? null : $category]);

        return redirect()->back();
    }


    public function index(Request $request)
    {

        $colors = [
            'DEBUG' => 'light',
            'INFO' => 'light',
            'NOTICE' => 'info',
            'WARNING' => 'warning',
            'ERROR' => 'warning',
            'CRITICAL' => 'danger',
            'ALERT' => 'danger',
            'EMERGENCY' => 'danger',
        ];



        $categories = LogCategories::withOther();
        $activeLevel = session('log_filter');
        $activeCategory = LogCategories::exists(session('log_category')) ? session('log_category') : null;

        $logs = LogToDB::model()
            ->when($activeLevel, function($query) use ($activeLevel) {
                return $query->where('level_name', $activeLevel);
            })
            ->when($activeCategory, function($query) use ($activeCategory) {
                return LogCategories::applyFilter($query, $activeCategory);
            })
            ->orderBy('created_at', 'desc')->paginate(30);

        return view('logs.index', compact('logs', 'colors', 'categories', 'activeLevel', 'activeCategory'));

    }

    public function download(Request $request)
    {
        $start_date = $request->input('start_date') ? $request->input('start_date') : now()->subDays(30)->format('Y-m-d');
        $end_date = $request->input('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $level = $request->input('level') ? Str::upper($request->input('level')) : "";
        $category = $request->input('category', session('log_category'));

        $logs = LogToDB::model()
            ->when($request->has('start_date'), function($query) use ($start_date) {
                return $query->whereDate('created_at', '>=', $start_date);
            })
            ->when($request->has('end_date'), function($query) use ($end_date) {
                return $query->whereDate('created_at', '<=', $end_date);
            })
            ->when($request->has('level'), function($query) use ($level) {
                if ($level != "") {
                    return $query->where('level_name', $level);
                }

                if (session('log_filter')) {
                    return $query->where('level_name', session('log_filter'));
                }

                return $query;
            })
            ->when(LogCategories::exists($category), function($query) use ($category) {
                return LogCategories::applyFilter($query, $category);
            })
            ->orderBy('created_at', 'desc')->get();

        $filename = 'logs_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new LogExport($logs), $filename);

    }
}
