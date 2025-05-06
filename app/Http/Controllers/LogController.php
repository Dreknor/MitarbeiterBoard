<?php

namespace App\Http\Controllers;

use App\Exports\LogExport;
use App\Models\User;
use danielme85\LaravelLogToDB\LogToDB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view logs');
    }

    public function index(Request $request)
    {
        $logs = LogToDB::model()->orderBy('created_at', 'desc', )->paginate(30);
        return view('logs.index', compact('logs'));

    }

    public function download(Request $request)
    {
        $start_date = $request->input('start_date') ? $request->input('start_date') : now()->subDays(30)->format('Y-m-d');
        $end_date = $request->input('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $level = $request->input('level') ? $request->input('level'): "";

        $logs = LogToDB::model()
            ->when($request->has('start_date'), function($query) use ($start_date) {
                return $query->whereDate('created_at', '>=', $start_date);
            })
            ->when($request->has('end_date'), function($query) use ($end_date) {
                return $query->whereDate('created_at', '<=', $end_date);
            })
            ->when($request->has('level'), function($query) use ($level) {
                if ($level != "") {
                    return $query->where('level', $level);
                }
            })
            ->orderBy('created_at', 'desc', )->get();

        $filename = 'logs_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new LogExport($logs), $filename);

    }
}
