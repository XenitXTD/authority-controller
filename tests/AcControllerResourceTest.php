<?php

use Mockery as m;

class AcControllerResourceTest extends AcTestCase
{
    use AuthorityControllerHelpers;

    public function setUp()
    {
        parent::setUp();

        $this->params = ['controller' => 'projects'];
        $this->controllerClass = $this->mock('ProjectsController');
        $this->controller = App::make('ProjectsController');

        $this->user = $this->getUserWithRole('admin');
        $this->authority = $this->getAuthority($this->user);

        $this->controller->shouldReceive('getParams')->andReturnUsing(function() { return $this->params; } );
        $this->controller->shouldReceive('getCurrentAuthority')->andReturnUsing(function() { return $this->authority; });
        // $this->controllerClass->shouldReceive('cancanSkipper')->andReturnUsing( function() { return ['authorize' => [], 'load' => []] });;
    }

    // Should load the resource into an instance variable if $params['id'] is specified
    public function testLoadResourceInstanceWithParamId()
    {
        $projectAttributes = ['id' => 2, 'name' => 'Test AuthorityController package', 'priority' => 1];
        $project = $this->buildModel('Project', $projectAttributes);
        $this->params = array_merge($this->params, ['action' => 'show', 'id' => $project->id]);

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project'), $project);
    }


    // Should not load resource into an instance variable if already set
    public function testNotLoadResourceInstanceIfAlreadySet()
    {
        $this->params = array_merge($this->params, ['action' => 'show', 'id' => '123']);

        $this->setProperty($this->controller, 'project', 'some_project');

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project'), 'some_project');
    }

    // Should properly load resource for namespaced controller
    public function testLoadResourceForNamespacedController()
    {
        $projectAttributes = ['id' => 2, 'name' => 'Test AuthorityController package', 'priority' => 1];
        $project = $this->buildModel('Project', $projectAttributes);
        $this->params = array_merge(
            $this->params,
            ['controller' => 'admin/projects', 'action' => 'show', 'id' => $project->id]
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project'), $project);
    }

    // Should attempt to load a resource with the same namespace as the controller when using \\ for namespace
    public function testLoadResourceWithSameNamespaceAsControllerWithBackslashedNamespace()
    {
        $projectAttributes = ['id' => 2, 'name' => 'Test AuthorityController package', 'priority' => 1];
        $project = $this->buildModel('MyEngine\Project', $projectAttributes);
        $this->params = array_merge(
            $this->params,
            ['controller' => 'MyEngine\ProjectsController', 'action' => 'show', 'id' => $project->id]
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project'), $project);
    }

    // Laravel includes namespace in params, see CanCan issue #349
    // Should store through the namespaced params
    public function testCreateThroughNamespacedParams()
    {
        // namespace MyEngine;
        // class Project extends \Project {}
        $projectAttributes = ['id' => 2, 'name' => 'Test AuthorityController package', 'priority' => 1];
        $project = $this->buildModel('MyEngine\Project', $projectAttributes);

        $actionParams = ['my_engine_project' => ['name' => 'foobar']];
        $this->params = array_merge(
            $this->params,
            array_merge(['controller' => 'MyEngine\ProjectsController', 'action' => 'store'], $actionParams)
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project')->name, "foobar");
    }

    // Should properly load resource for namespaced controller when using '::' for namespace
    public function testProperlyLoadResourceNamespacedControllerWithBackslashedNamespace()
    {
        $projectAttributes = ['id' => 2, 'name' => 'Test AuthorityController package', 'priority' => 1];
        $project = $this->buildModel('Project', $projectAttributes);
        $this->params = array_merge(
            $this->params,
            ['controller' => 'Admin\ProjectsController', 'action' => 'show', 'id' => $project->id]
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project'), $project);
    }

    // Has the specified nested resource_class when using / for namespace
    public function testHasSpecifiedNestedResourceClassWithSlashedNamespace()
    {
        // namespace Admin;
        // class Dashboard {}
        $dashboardAttributes = ['id' => 2, 'name' => 'Test AuthorityController package', 'priority' => 1];
        $dashboard = $this->buildModel('Admin\Dashboard', $dashboardAttributes);

        $this->authority->allow('index', 'admin/dashboard');

        $this->params = array_merge(
            $this->params,
            ['controller' => 'admin/dashboard', 'action' => 'index']
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller, ['authorize' => true]);

        $this->assertEquals($this->invokeMethod($resource, 'getResourceClass'), 'Admin\Dashboard');
    }

    // Should build a new resource with hash if params[:id] is not specified
    public function testBuildNewResourceWithAssociativeArrayIfParamIdIsNotSpecified()
    {
        $this->buildModel('Project');
        $this->params = array_merge(
            $this->params,
            ['action' => 'store', 'project' => ['name' => 'foobar']]
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project')->name, "foobar");
    }

    // Should build a new resource for namespaced model with hash if params[:id] is not specified
    public function testBuildNewResourceNamespacedModelWithAssociativeArrayIfParamIdIsNotSpecified()
    {
        $this->buildModel('\Sub\Project');
        $this->params = array_merge(
            $this->params,
            ['action' => 'store', 'sub_project' => ['name' => 'foobar']]
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller, ['class' => '\Sub\Project']);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project')->name, "foobar");
    }

    // Should build a new resource for namespaced controller and namespaced model with hash if params[:id] is not specified
    public function testBuildNewResourceForNamespacedControllerAndNamespacedModelWithAssociativeArrayIfParamIdIsNotSpecified()
    {
        $this->buildModel('Project');
        $this->params = array_merge(
            $this->params,
            ['controller' => 'Admin\SubProjectsController', 'action' => 'store', 'sub_project' => ['name' => 'foobar']]
        );

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller, ['class' => 'Project']);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'subProject')->name, "foobar");
    }

    // Should build a collection when on index action
    public function testBuildCollectionWhenOnIndexAction()
    {
        $project = $this->mock('Project');
        $project->shouldReceive('get')->andReturn("found_projects");

        $this->params['action'] = "index";

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller, 'project');
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project'), null);
        $this->assertEquals($this->getProperty($this->controller, 'projects'), "found_projects");
    }

    // Should not use accessible_by when defining authorityRules through a Closure
    public function testShouldLoadCollectionResourceWhenDefiningAuthorityRulesThroughClosure()
    {
        $project = $this->mock('Project');
        $project->shouldReceive('get')->andReturn("found_projects");

        $this->params['action'] = "index";

        $this->authority->allow('read', 'Project', function($p) { return false; });

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->loadResource();

        $this->assertEquals($this->getProperty($this->controller, 'project'), null);
        $this->assertFalse(property_exists($this->controller, 'projects'));
    }


    /**
     * Should not authorize single resource in collection action
     *
     * @expectedException Efficiently\AuthorityController\Exceptions\AccessDenied
     */
    public function testShouldNotAuthorizeSingleResourceInCollectionAction()
    {
        $this->params['action'] = "index";
        $this->setProperty($this->controller, 'project', 'some_project');

        $this->controller->shouldReceive('authorize')->with('index', 'Project')->andReturnUsing(function() {
            throw new Efficiently\AuthorityController\Exceptions\AccessDenied;
        });

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->authorizeResource();
    }

    /**
     * Should authorize parent resource in collection action
     * @expectedException Efficiently\AuthorityController\Exceptions\AccessDenied
     */
    public function testShouldAuthorizeParentResourceInCollectionAction()
    {
        $this->params['action'] = "index";
        $this->setProperty($this->controller, 'category', 'some_category');

        $this->controller->shouldReceive('authorize')->with('show', 'some_category')->andReturnUsing(function() {
            throw new Efficiently\AuthorityController\Exceptions\AccessDenied;
        });

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller, 'category', ['parent' => true]);
        $resource->authorizeResource();
    }

    /**
     * Should perform authorization using controller action and loaded model
     * @expectedException Efficiently\AuthorityController\Exceptions\AccessDenied
     */
    public function testShouldPerformAuthorizationUsingControllerActionAndLoadedModel()
    {
        $this->params = array_merge($this->params, array_merge(['action' => 'show', 'id' => '123']));
        $this->setProperty($this->controller, 'project', 'some_project');

        $this->controller->shouldReceive('authorize')->with('show', 'some_project')->andReturnUsing(function() {
            throw new Efficiently\AuthorityController\Exceptions\AccessDenied;
        });

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->authorizeResource();
    }

    /**
     * Should perform authorization using controller action and non loaded model
     * @expectedException Efficiently\AuthorityController\Exceptions\AccessDenied
     */
    public function testShouldPerformAuthorizationUsingControllerActionAndNonLoadedModel()
    {
        $this->params = array_merge($this->params, array_merge(['action' => 'show', 'id' => '123']));

        $this->controller->shouldReceive('authorize')->with('show', 'Project')->andReturnUsing(function() {
            throw new Efficiently\AuthorityController\Exceptions\AccessDenied;
        });

        $resource = new Efficiently\AuthorityController\ControllerResource($this->controller);
        $resource->authorizeResource();
    }

    // Should call loadResource and authorizeResource for loadAndAuthorizeResource
    public function testShouldCallLoadResourceAndAuthorizeResourceForLoadAndAuthorizeResource()
    {
        $this->params = array_merge($this->params, array_merge(['action' => 'show', 'id' => '123']));

        $className = "Efficiently\\AuthorityController\\ControllerResource";
        $mock = m::mock($className)->makePartial();
        App::instance($className, $mock);
        $resource = App::make($className, [$this->controller]);

        $resource->shouldReceive('loadResource')->once();
        $resource->shouldReceive('authorizeResource')->once();
        $resource->loadAndAuthorizeResource();
    }

    // TODO: Port more tests, see: https://github.com/ryanb/cancan/blob/master/spec/cancan/controller_resource_spec.rb#L183

    protected function buildModel($modelName, $modelAttributes = [])
    {
        $modelAttributes = $modelAttributes;
        $mock = $this->mock($modelName);
        $model = $this->fillMock($mock, $modelAttributes);

        $mock->shouldReceive('where->firstOrFail')->/*once()->*/andReturn($model);
        $mock->shouldReceive('save')->/*once()->*/andReturn(true);
        $mock->shouldReceive('fill')->with(m::type('array'))->andReturnUsing(function($attributes) use($mock) {
            $this->fillMock($mock, $attributes);
            return $mock;
        });

        $models = new Illuminate\Database\Eloquent\Collection();
        $models->add($model);
        $mock->shouldReceive('get')->andReturn($models);
        $mock->shouldReceive('all')->andReturn($models);

        return $model;
    }

}
