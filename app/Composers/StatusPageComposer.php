<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Composers;

use CachetHQ\Cachet\Models\Component;
use CachetHQ\Cachet\Models\ComponentGroup;
use CachetHQ\Cachet\Models\Incident;
use Illuminate\Contracts\View\View;

class StatusPageComposer
{
    /**
     * Index page view composer.
     *
     * @param \Illuminate\Contracts\View\View $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $components = Component::enabled()->get();
        foreach ($components as $key => $component )
	{
            $group_id = $component['group_id'];
            $group = ComponentGroup::find($group_id);
            $group_name = $group['name'];
            $teamFilter = $_SESSION["teamFilter"];
            if(substr($group_name, 0, strlen($teamFilter)) === $teamFilter)
            {
            } else {
                unset($components[$key]);
            }
        }
        $filteredComponents = $components->count();
        $totalComponents = Component::enabled()->count();
        $totalComponents = $filteredComponents;

        $majorOutages = Component::enabled()->status(4)->count();
        $majorOutagesFilter = Component::enabled()->status(4)->get();

        foreach ($majorOutagesFilter as $key => $component )
	{
            $group_id = $component['group_id'];
            $group = ComponentGroup::find($group_id);
            $group_name = $group['name'];
            $teamFilter = $_SESSION["teamFilter"];
            if(substr($group_name, 0, strlen($teamFilter)) === $teamFilter)
            {
            } else {
                unset($majorOutagesFilter[$key]);
            }
        }
        $majorOutages = $majorOutagesFilter->count();
        $isMajorOutage = $totalComponents ? ($majorOutages / $totalComponents) >= 0.5 : false;
        // Default data
        $withData = [
            'system_status'  => 'info',
            'system_message' => trans_choice('cachet.service.bad', $totalComponents),
            'favicon'        => 'favicon-high-alert',
        ];

        $a = Component::enabled()->notStatus(1)->get();
        foreach ($a as $key => $component )
		{
            $group_id = $component['group_id'];
            $group = ComponentGroup::find($group_id);
            $group_name = $group['name'];
            $teamFilter = $_SESSION["teamFilter"];
            if(substr($group_name, 0, strlen($teamFilter)) === $teamFilter)
            {
            } else {
                unset($a[$key]);
            }
        }


        if ($isMajorOutage) {
            $withData = [
                'system_status'  => 'danger',
                'system_message' => trans_choice('cachet.service.major', $totalComponents),
                'favicon'        => 'favicon-high-alert',
            ];
        } elseif ($a->count() === 0) {
            // If all our components are ok, do we have any non-fixed incidents?
            $incidents = Incident::notScheduled()->orderBy('created_at', 'desc')->get()->filter(function ($incident) {
                return $incident->status > 0;
            });
            $incidentCount = $incidents->count();

            foreach ($incidents as $key => $value )
            {
                $component_id = $value['component_id'];
                $group_id = $component['group_id'];
                $group = ComponentGroup::find($group_id);
                $group_name = $group['name'];
                $teamFilter = $_SESSION["teamFilter"];
                if(substr($group_name, 0, strlen($teamFilter)) === $teamFilter)
                {
                } else {
                    unset($incidents[$key]);
                }
            }
            $incidentCount = $incidents->count();

            if ($incidentCount === 0 || ($incidentCount >= 1 && (int) $incidents->first()->status === 4)) {
                $withData = [
                    'system_status'  => 'success',
                    'system_message' => trans_choice('cachet.service.good', $totalComponents),
                    'favicon'        => 'favicon',
                ];
            }
        } else {
            if (Component::enabled()->whereIn('status', [2, 3])->count() > 0) {
                $withData['favicon'] = 'favicon-medium-alert';
            }
        }

        // Scheduled maintenance code.
        $scheduledMaintenance = Incident::scheduled()->orderBy('scheduled_at')->get();

        // Component & Component Group lists.
        $usedComponentGroups = Component::enabled()->where('group_id', '>', 0)->groupBy('group_id')->pluck('group_id');
        $componentGroups = ComponentGroup::whereIn('id', $usedComponentGroups)->orderBy('order')->get();
        $ungroupedComponents = Component::enabled()->where('group_id', 0)->orderBy('order')->orderBy('created_at')->get();

        $view->with($withData)
            ->withComponentGroups($componentGroups)
            ->withUngroupedComponents($ungroupedComponents)
            ->withScheduledMaintenance($scheduledMaintenance);
    }
}
