<?php

namespace App\Policies;

use App\Models\Developer;
use App\Models\Mod;

class ModPolicy
{
    public function update(Developer $developer, Mod $mod): bool
    {
        return (int) $mod->developer_id === (int) $developer->id;
    }

    public function uploadVersion(Developer $developer, Mod $mod): bool
    {
        return (int) $mod->developer_id === (int) $developer->id;
    }

    public function delete(Developer $developer, Mod $mod): bool
    {
        return (int) $mod->developer_id === (int) $developer->id;
    }
}

