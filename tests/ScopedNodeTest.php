<?php

/**
 * @noinspection PhpPossiblePolymorphicInvocationInspection PHPStorm is unaware that Category::query returns a
 *                                                          Nested Set query and tries to resolve the method to
 *                                                          the normal query builder
 * @noinspection PhpUnhandledExceptionInspection            Tests are supposed to throw exceptions
 */

namespace Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Kalnoy\Nestedset\NestedSet;
use PHPUnit\Framework\TestCase;
use Tests\Models\MenuItem;

class ScopedNodeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $schema = Capsule::schema();

        $schema->dropIfExists('menu_items');

        Capsule::disableQueryLog();

        $schema->create('menu_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id');
            $table->string('title')->nullable();
            NestedSet::columns($table);
        });

        Capsule::enableQueryLog();
    }

    public function setUp(): void
    {
        $data = include __DIR__ . '/Data/menu_items.php';

        Capsule::table('menu_items')->insert($data);

        Capsule::flushQueryLog();

        MenuItem::resetActionsPerformed();

        date_default_timezone_set('America/Denver');
    }

    public function tearDown(): void
    {
        Capsule::table('menu_items')->truncate();
    }

    public function assertTreeNotBroken($menuId)
    {
        $this->assertFalse(MenuItem::scoped([ 'menu_id' => $menuId ])->isBroken());
    }

    public function testNotBroken()
    {
        $this->assertTreeNotBroken(1);
        $this->assertTreeNotBroken(2);
    }

    public function testMovingNodeNotAffectingOtherMenu()
    {
        $node = MenuItem::query()->where('menu_id', '=', 1)->first();

        $node->down();

        $node = MenuItem::query()->where('menu_id', '=', 2)->first();

        $this->assertEquals(1, $node->getLft());
    }

    public function testScoped()
    {
        $node = MenuItem::scoped([ 'menu_id' => 2 ])->first();

        $this->assertEquals(3, $node->getKey());
    }

    public function testSiblings()
    {
		/** @var MenuItem $node */
        $node = MenuItem::query()->find(1);

        $result = $node->getSiblings();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());

        $result = $node->getNextSiblings();

        $this->assertEquals(2, $result->first()->getKey());

	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(2);

        $result = $node->getPrevSiblings();

        $this->assertEquals(1, $result->first()->getKey());
    }

    public function testDescendants()
    {
	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(2);

        $result = $node->getDescendants();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(5, $result->first()->getKey());

	    /** @var MenuItem $node */
        $node = MenuItem::scoped([ 'menu_id' => 1 ])->with('descendants')->find(2);

        $result = $node->descendants;

        $this->assertEquals(1, $result->count());
        $this->assertEquals(5, $result->first()->getKey());
    }

    public function testAncestors()
    {
	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(5);

        $result = $node->getAncestors();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());

	    /** @var MenuItem $node */
        $node = MenuItem::scoped([ 'menu_id' => 1 ])->with('ancestors')->find(5);

        $result = $node->ancestors;

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());
    }

    public function testDepth()
    {
	    /** @var MenuItem $node */
		$node = MenuItem::scoped([ 'menu_id' => 1 ])->withDepth()->where('id', '=', 5)->first();

        $this->assertEquals(1, $node->depth);

	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(2);

        $result = $node->children()->withDepth()->get();

        $this->assertEquals(1, $result->first()->depth);
    }

    public function testSaveAsRoot()
    {
	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(5);

        $node->saveAsRoot();

        $this->assertEquals(5, $node->getLft());
        $this->assertEquals(null, $node->parent_id);

        $this->assertOtherScopeNotAffected();
    }

    public function testInsertion()
    {
        $node = MenuItem::create([ 'menu_id' => 1, 'parent_id' => 5 ]);

        $this->assertEquals(5, $node->parent_id);
        $this->assertEquals(5, $node->getLft());

        $this->assertOtherScopeNotAffected();
    }

    public function testInsertionToParentFromOtherScope()
    {
        $this->expectException(ModelNotFoundException::class);

        MenuItem::create([ 'menu_id' => 2, 'parent_id' => 5 ]);
    }

    public function testDeletion()
    {
        MenuItem::query()->find(2)->delete();

	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(1);

        $this->assertEquals(2, $node->getRgt());

        $this->assertOtherScopeNotAffected();
    }

    public function testMoving()
    {
	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(1);
        $this->assertTrue($node->down());

        $this->assertOtherScopeNotAffected();
    }

    protected function assertOtherScopeNotAffected()
    {
	    /** @var MenuItem $node */
        $node = MenuItem::query()->find(3);

        $this->assertEquals(1, $node->getLft());
    }

    // Commented, cause there is no assertion here and otherwise the test is marked as risky in PHPUnit 7.
    // What's the purpose of this method? @todo: remove/update?
    /*public function testRebuildsTree()
    {
        $data = [];
        MenuItem::scoped([ 'menu_id' => 2 ])->rebuildTree($data);
    }*/

    public function testAppendingToAnotherScopeFails()
    {
        $this->expectException(\LogicException::class);

	    /** @var MenuItem $a */
        $a = MenuItem::query()->find(1);
	    /** @var MenuItem $b */
        $b = MenuItem::query()->find(3);

        $a->appendToNode($b)->save();
    }

    public function testInsertingBeforeAnotherScopeFails()
    {
        $this->expectException(\LogicException::class);

	    /** @var MenuItem $a */
        $a = MenuItem::query()->find(1);
	    /** @var MenuItem $b */
        $b = MenuItem::query()->find(3);

        $a->insertAfterNode($b);
    }
}
