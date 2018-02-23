<?php

namespace Icinga\Module\Vspheredb\Web\Table\Objects;

use dipl\Html\Icon;
use Icinga\Date\DateFormatter;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\Web\Table\SimpleColumn;
use Icinga\Module\Vspheredb\Web\Widget\SimpleUsageBar;
use Icinga\Module\Vspheredb\Web\Widget\SpectreMelddownBiosInfo;
use Icinga\Util\Format;
use dipl\Html\Link;

class HostsTable extends ObjectsTable
{
    protected function initialize()
    {
        $this->addAvailableColumns([
            (new SimpleColumn('object_name', $this->translate('Name'), [
                'object_name' => 'o.object_name',
                'uuid'        => 'o.uuid',
            ]))->setRenderer(function ($row) {
                return Link::create(
                    $row->object_name,
                    'vspheredb/host',
                    ['uuid' => bin2hex($row->uuid)]
                );
            }),
            new SimpleColumn('sysinfo_vendor', $this->translate('Vendor'), 'h.sysinfo_vendor'),
            new SimpleColumn('sysinfo_model', $this->translate('Model'), 'h.sysinfo_model'),
            new SimpleColumn('bios_version', $this->translate('BIOS Version'), 'h.bios_version'),
            (new SimpleColumn('bios_release_date', $this->translate('BIOS Release Date'), 'h.bios_release_date'))
                ->setRenderer(function ($row) {
                    return DateFormatter::formatDate(strtotime($row->bios_release_date));
                }),
            (new SimpleColumn('cpu_usage', $this->translate('CPU Usage'), [
                'cpu_usage' => 'hqs.overall_cpu_usage',
                'cpu_total' => '(hardware_cpu_cores * hardware_cpu_mhz)',
            ]))->setRenderer(function ($row) {
                $title = sprintf('%s / %s MHz', $row->cpu_usage, $row->cpu_total);

                return new SimpleUsageBar($row->cpu_usage, $row->cpu_total, $title);
            })->setSortExpression(
                'hqs.overall_cpu_usage / (h.hardware_cpu_cores * h.hardware_cpu_mhz)'
            )->setDefaultSortDirection('DESC'),
            (new SimpleColumn('memory_usage', $this->translate('Memory Usage'), [
                'hardware_memory_size_mb' => 'h.hardware_memory_size_mb',
                'memory_usage_mb'         => 'hqs.overall_memory_usage_mb',
            ]))->setRenderer(function ($row) {
                $used = $row->memory_usage_mb * 1024 * 1024;
                $total = $row->hardware_memory_size_mb * 1024 * 1024;
                $title = sprintf(
                    '%s / %s',
                    Format::bytes($used),
                    Format::bytes($total)
                );

                return new SimpleUsageBar($used, $total, $title);
            })->setSortExpression(
                '(hqs.overall_memory_usage_mb / h.hardware_memory_size_mb)'
            )->setDefaultSortDirection('DESC'),
            new SimpleColumn('hardware_cpu_cores', $this->translate('CPU Cores'), 'h.hardware_cpu_cores'),
            new SimpleColumn('vms_cnt_cpu', $this->translate('VM CPUs'), 'vms.cnt_cpu'),
            (new SimpleColumn('hardware_memory_size_mb', $this->translate('Memory'), 'h.hardware_memory_size_mb'))
                ->setRenderer(function ($row) {
                    return Format::bytes($row->hardware_memory_size_mb * 1024 * 1024, Format::STANDARD_IEC);
                }),
            (new SimpleColumn('vms_memorymb', $this->translate('VMs Memory'), 'vms.memorymb'))
                ->setRenderer(function ($row) {
                    return Format::bytes($row->vms_memorymb * 1024 * 1024, Format::STANDARD_IEC);
                }),
            new SimpleColumn('vms_cnt', $this->translate('VMs'), 'vms.cnt'),
            (new SimpleColumn('spectre_meltdown', $this->translate('Spectre / Meltdown'), [
                'sysinfo_vendor'    => 'h.sysinfo_vendor',
                'sysinfo_model'     => 'h.sysinfo_model',
                'bios_version'      => 'h.bios_version',
                'bios_release_date' => 'h.bios_release_date',
            ]))->setRenderer(function ($row) {
                $host = HostSystem::create((array) [
                    'sysinfo_vendor'    => $row->sysinfo_vendor,
                    'sysinfo_model'     => $row->sysinfo_model,
                    'bios_version'      => $row->bios_version,
                    'bios_release_date' => $row->bios_release_date,
                ]);

                return new SpectreMelddownBiosInfo($host);
            }),
            (new SimpleColumn('runtime_power_state', $this->translate('Power'), 'runtime_power_state'))
                ->setRenderer(function ($row) {
                    switch ($row->runtime_power_state) {
                        case 'poweredOn':
                            return Icon::create('off', [
                                'class' => $row->runtime_power_state
                            ]);
                        default:
                            return $row->runtime_power_state;
                    }
                })
        ]);
    }

    public function getDefaultColumnNames()
    {
        return [
            'object_name',
            'cpu_usage',
            'memory_usage',
            'vms_cnt_cpu',
        ];
    }

    public function prepareQuery()
    {
        $vms = $this->db()->select()->from(
            ['vc' => 'virtual_machine'],
            [
                'cnt'               => 'COUNT(*)',
                'cnt_cpu'           => 'SUM(vc.hardware_numcpu)',
                'memorymb'          => 'SUM(vc.hardware_memorymb)',
                'runtime_host_uuid' => 'vc.runtime_host_uuid',
            ]
        )->group('vc.runtime_host_uuid');

        $query = $this->db()->select()->from(
            ['o' => 'object'],
            [
                'runtime_power_state' => 'h.runtime_power_state',
                'overall_status'      => 'o.overall_status',
            ] + $this->getRequiredDbColumns()
        )->join(
            ['h' => 'host_system'],
            'o.uuid = h.uuid',
            []
        )->join(
            ['hqs' => 'host_quick_stats'],
            'h.uuid = hqs.uuid',
            []
        )->joinLeft(
            ['vms' => $vms],
            'vms.runtime_host_uuid = h.uuid',
            []
        )->limit(100);

        if ($this->parentUuids) {
            $query->where('o.parent_uuid IN (?)', $this->parentUuids);
        }

        return $query;
    }

    public function renderRow($row)
    {
        return parent::renderRow($row)->addAttributes(['class' => [
            $row->runtime_power_state,
            $row->overall_status
        ]]);
    }
}
