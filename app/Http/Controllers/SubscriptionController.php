<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Group;
use App\Models\Theme;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function add($type, $id){

        if ($type == 'theme'){
            $type = Theme::class;

            $theme = Theme::find($id);
            if (!auth()->user()->groups()->contains($theme->group)){
                return redirect()->back()->with([
                    'type'    => 'warning',
                    'Meldung' => "Kein Zugriff auf diese Gruppe"
                ]);
            }



        } elseif ($type == 'group'){
            $type = Group::class;
            $group = Group::where('name', $id)->first();

            if (!auth()->user()->groups()->contains($group)){
                return redirect()->back()->with([
                    'type'    => 'warning',
                    'Meldung' => "Kein Zugriff auf diese Gruppe"
                ]);
            }

            $id = $group->id;

        } else {
            return redirect()->back()->with([
               'type'=>'warning',
               'Meldung' => "Abo für ".$type." nicht möglich"
            ]);
        }

        $subscription = auth()->user()->subscriptions()->where('subscriptionable_type', $type)->where('subscriptionable_id', $id)->first();

        if ($subscription == null){

            $subscription = new Subscription([
               'users_id' => auth()->id(),
               'subscriptionable_type' => $type,
                'subscriptionable_id'=>$id
            ]);

            $subscription->save();
        }

        return redirect()->back()->with([
            'type'=>'success',
            'Meldung' => "Abo gespeichert"
        ]);

    }

    public function remove($type, $id){

        if ($type == 'theme'){
            $type = Theme::class;
        } elseif ($type == 'group'){
            $type = Group::class;
        } else {
            return redirect()->back()->with([
               'type'=>'warning',
               'Meldung' => "Kein Abo vorhanden"
            ]);
        }

        $subscription = auth()->user()->subscriptions()->where('subscriptionable_type', $type)->where('subscriptionable_id', $id)->first();

        if ($subscription != null){

            $subscription->delete();
        }

        return redirect()->back()->with([
            'type'=>'success',
            'Meldung' => "Abo entfernt"
        ]);

    }
}
