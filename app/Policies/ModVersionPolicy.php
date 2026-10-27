<?php

namespace App\Policies;

use App\Models\Developer;
use App\Models\ModVersion;

class ModVersionPolicy
{
    public function viewReport(?Developer $developer, ModVersion $version): bool
    {
        if ($version->mod && $version->mod->published_at !== null) {
            return true;
        }

        return $developer !== null
            && $version->mod !== null
            && (int) $version->mod->developer_id === (int) $developer->id;
    }

    public function delete(Developer $developer, ModVersion $version): bool
    {
        return $version->mod !== null
            && (int) $version->mod->developer_id === (int) $developer->id;
    }
}

