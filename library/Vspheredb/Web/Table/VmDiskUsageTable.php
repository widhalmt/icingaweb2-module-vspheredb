<?php

namespace Icinga\Module\Vspheredb\Web\Table;

use dipl\Html\Html;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\Web\Widget\SimpleUsageBar;
use Icinga\Util\Format;
use dipl\Web\Table\ZfQueryBasedTable;

class VmDiskUsageTable extends ZfQueryBasedTable
{
    /** @var VirtualMachine */
    protected $vm;

    /** @var string */
    protected $uuid;

    private $root;

    public static function create(VirtualMachine $vm)
    {
        $tbl = new static($vm->getConnection());
        return $tbl->setVm($vm);
    }

    protected function setVm(VirtualMachine $vm)
    {
        $this->vm = $vm;
        $this->uuid = $vm->get('uuid');

        return $this;
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Disk'),
            $this->translate('Size'),
            $this->translate('Free space'),
            $this->translate('Usage'),
        ];
    }

    public function renderRow($row)
    {
        $caption = $row->disk_path;

        if ($caption === '/') {
            $this->root = $row;
        }

        $free = Format::bytes($row->free_space, Format::STANDARD_IEC)
            . sprintf(' (%0.3f%%)', ($row->free_space / $row->capacity) * 100);

        $tr = $this::tr([
            // TODO: move to CSS
            $this::td($caption, ['style' => 'overflow: hidden; display: inline-block; height: 2em; min-width: 8em;']),
            $this::td(Format::bytes($row->capacity, Format::STANDARD_IEC), ['style' => 'white-space: pre;']),
            $this::td($free, ['style' => 'width: 25%;']),
            $this::td($this->makeDisk($row), ['style' => 'width: 25%;'])
        ]);

        return $tr;
    }

    protected function makeDisk($disk)
    {
        $used = $disk->capacity - $disk->free_space;

        return new SimpleUsageBar($used, $disk->capacity, $disk->disk_path);
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            'vm_disk_usage',
            ['disk_path', 'capacity', 'free_space']
        )->where(
            'vm_uuid = ?',
            $this->uuid
        )->order('disk_path');
    }
}