<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\Node;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @phpstan-implements Node<MenuItem>
 */
class MenuItem extends Model implements Node
{
	/** @phpstan-use NodeTrait<MenuItem> */
    use NodeTrait;

    public $timestamps = false;

    protected $fillable = ['menu_id','parent_id'];

    public static function resetActionsPerformed(): void
    {
        static::$actionsPerformed = 0;
    }

    protected function getScopeAttributes(): array
    {
        return ['menu_id'];
    }

}
