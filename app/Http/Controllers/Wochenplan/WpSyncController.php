<?php
namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Wochenplan\WpPlan;
use Illuminate\Http\Request;

class WpSyncController extends Controller
{
    public function syncFach(WpPlan $wpPlan, int $fachId)
    {
        if (!$wpPlan->isSchuelerplan() || !$wpPlan->parent_plan_id) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Synchronisation nur fuer Kinderplaene moeglich.',
            ]);
        }

        $wpPlan->syncFachVonParent($fachId);

        $wpPlan->load('planFaecher.fach');
        $fachName = $wpPlan->planFaecher->firstWhere('wp_fach_id', $fachId)?->display_name ?? 'Fach';

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => "Aufgaben fuer $fachName vom Klassenplan synchronisiert.",
        ]);
    }

    public function syncAll(WpPlan $wpPlan)
    {
        if (!$wpPlan->isSchuelerplan() || !$wpPlan->parent_plan_id) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Synchronisation nur fuer Kinderplaene moeglich.',
            ]);
        }

        $parentPlan = $wpPlan->parentPlan()->with('planFaecher')->first();
        if ($parentPlan) {
            foreach ($parentPlan->planFaecher as $parentFach) {
                if ($parentFach->wp_fach_id) {
                    $wpPlan->syncFachVonParent($parentFach->wp_fach_id);
                }
            }
        }

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Alle Faecher vom Klassenplan synchronisiert.',
        ]);
    }
}
