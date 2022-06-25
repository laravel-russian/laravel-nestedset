<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeWithSoftDelete;
use Kalnoy\Nestedset\NodeWithSoftDeleteTrait;

/**
 * @phpstan-implements NodeWithSoftDelete<Category>
 *
 * @property int $id
 * @property string $name
 */
class Category extends Model implements NodeWithSoftDelete {
	/** @phpstan-use NodeWithSoftDeleteTrait<Category> */
	use NodeWithSoftDeleteTrait;

    protected $fillable = array('name', 'parent_id');

    public $timestamps = false;

    public static function resetActionsPerformed(): void
    {
        static::$actionsPerformed = 0;
    }
}
