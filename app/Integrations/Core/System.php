<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Integrations\Core;

use CachetHQ\Cachet\Integrations\Contracts\System as SystemContract;
use CachetHQ\Cachet\Models\Component;
use CachetHQ\Cachet\Models\ComponentGroup;
use CachetHQ\Cachet\Models\Incident;

/**
 * This is the core system class.
 *
 * @author James Brooks <james@alt-three.com>
 */
class System implements SystemContract
{
    /**
     * Get the entire system status.
     *
     * @return array
     */
    public function getStatus()
    {
        $enabledScope = Component::enabled();
        $components = Component::enabled()->get();
        $teamFilter = $_SESSION["teamFilter"];
        foreach ($components as $key => $component )
	    {
            $group_id = $component['group_id'];
            $group = ComponentGroup::find($group_id);
            $group_name = $group['name'];
            if(substr($group_name, 0, strlen($teamFilter)) != $teamFilter)
            {
                unset($components[$key]);
            }
        }
        $totalComponents = $components->count();

        $majorOutagesFilter = Component::enabled()->status(4)->get();
        foreach ($majorOutagesFilter as $key => $component )
        {
            $group_id = $component['group_id'];
            $group = ComponentGroup::find($group_id);
            $group_name = $group['name'];
            if(substr($group_name, 0, strlen($teamFilter)) != $teamFilter)
            {
                unset($majorOutagesFilter[$key]);
            }
        }
        $majorOutages = $majorOutagesFilter->count();

        $isMajorOutage = $totalComponents ? ($majorOutages / $totalComponents) >= 0.5 : false;

        // Default data
        $status = [
            'system_status'  => 'info',
            'system_message' => trans_choice('cachet.service.bad', $totalComponents),
            'favicon'        => 'favicon-high-alert',
        ];

        $componentsNotOK = Component::enabled()->notStatus(1)->get();
        foreach ($componentsNotOK as $key => $component )
        {
            $group_id = $component['group_id'];
            $group = ComponentGroup::find($group_id);
            $group_name = $group['name'];
            if(substr($group_name, 0, strlen($teamFilter)) != $teamFilter)
            {
                unset($componentsNotOK[$key]);
            }
        }

        if ($isMajorOutage) {
            $status = [
                'system_status'  => 'danger',
                'system_message' => trans_choice('cachet.service.major', $totalComponents),
                'favicon'        => 'favicon-high-alert',
            ];
        } elseif ($componentsNotOK->count() === 0) {
            // If all our components are ok, do we have any non-fixed incidents?
            $incidents = Incident::notScheduled()->orderBy('created_at', 'desc')->get()->filter(function ($incident) {
                return $incident->status > 0;
            });

            foreach ($incidents as $key => $value )
            {
                $component_id = $value['component_id'];
                $group_id = $component['group_id'];
                $group = ComponentGroup::find($group_id);
                $group_name = $group['name'];
                if(substr($group_name, 0, strlen($teamFilter)) != $teamFilter)
                {
                    unset($incidents[$key]);
                }
            }
            $incidentCount = $incidents->count();

            if ($incidentCount === 0 || ($incidentCount >= 1 && (int) $incidents->first()->status === 4)) {
                $status = [
                    'system_status'  => 'success',
                    'system_message' => trans_choice('cachet.service.good', $totalComponents),
                    'favicon'        => 'favicon',
                ];
            }
        } elseif (Component::enabled()->whereIn('status', [2, 3])->count() > 0) {
            $status['favicon'] = 'favicon-medium-alert';
        }

        return $status;
    }
}
