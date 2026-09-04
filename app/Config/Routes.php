<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

$routes->get('/', 'Home::index', ['as' => 'home']);
$routes->get('login', 'Auth\LoginController::show', ['as' => 'parent.login']);
$routes->post('login', 'Auth\LoginController::login', ['as' => 'parent.login.attempt']);

$routes->group('child', ['filter' => 'trusted-child-device'], static function (RouteCollection $routes): void {
    $routes->get('', 'Child\TodayController::index');
    $routes->get('today', 'Child\TodayController::index', ['as' => 'child.today']);
    $routes->get('progress', 'Child\ProgressController::index', ['as' => 'child.progress']);
    $routes->get('profile', 'Child\ProfileController::index', ['as' => 'child.profile']);
    $routes->post('profile', 'Child\ProfileController::update', ['as' => 'child.profile.update']);
    $routes->get('images/(:segment)', 'FamilyImageController::childImage/$1', ['as' => 'child.image']);
    $routes->get('rewards', 'Child\RewardController::index', ['as' => 'child.rewards']);
    $routes->post('rewards/(:num)/redeem', 'Child\RewardController::redeem/$1', ['as' => 'child.rewards.redeem']);
    $routes->post('tasks/(:num)/complete', 'Child\TaskController::complete/$1', ['as' => 'child.tasks.complete']);
    $routes->post('tasks/(:num)/undo', 'Child\TaskController::undo/$1', ['as' => 'child.tasks.undo']);
});

$routes->group('', ['filter' => 'parent-auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Parent\DashboardController::index', ['as' => 'parent.dashboard']);
    $routes->get('family-images/(:segment)', 'FamilyImageController::parentImage/$1', ['as' => 'parent.image']);
    $routes->get('children', 'Parent\ChildController::index', ['as' => 'parent.children']);
    $routes->get('children/new', 'Parent\ChildController::new', ['as' => 'parent.children.new']);
    $routes->post('children', 'Parent\ChildController::create', ['as' => 'parent.children.create']);
    $routes->get('children/(:num)/edit', 'Parent\ChildController::edit/$1', ['as' => 'parent.children.edit']);
    $routes->post('children/(:num)', 'Parent\ChildController::update/$1', ['as' => 'parent.children.update']);
    $routes->get('routines', 'Parent\RoutineController::index', ['as' => 'parent.routines']);
    $routes->get('points', 'Parent\PointController::index', ['as' => 'parent.points']);
    $routes->post('points/adjustments', 'Parent\PointController::adjust', ['as' => 'parent.points.adjust']);
    $routes->get('rewards', 'Parent\RewardController::index', ['as' => 'parent.rewards']);
    $routes->get('rewards/new', 'Parent\RewardController::new', ['as' => 'parent.rewards.new']);
    $routes->post('rewards', 'Parent\RewardController::create', ['as' => 'parent.rewards.create']);
    $routes->get('rewards/(:num)/edit', 'Parent\RewardController::edit/$1', ['as' => 'parent.rewards.edit']);
    $routes->post('rewards/(:num)', 'Parent\RewardController::update/$1', ['as' => 'parent.rewards.update']);
    $routes->post('rewards/(:num)/archive', 'Parent\RewardController::archive/$1', ['as' => 'parent.rewards.archive']);
    $routes->post('reward-redemptions/(:num)/approve', 'Parent\RewardController::approve/$1', ['as' => 'parent.reward-redemptions.approve']);
    $routes->post('reward-redemptions/(:num)/reject', 'Parent\RewardController::reject/$1', ['as' => 'parent.reward-redemptions.reject']);
    $routes->get('ranking', 'Parent\RankingController::index', ['as' => 'parent.ranking']);
    $routes->get('reports', 'Parent\ReportController::index', ['as' => 'parent.reports']);
    $routes->get('routines/new', 'Parent\RoutineController::new', ['as' => 'parent.routines.new']);
    $routes->post('routines', 'Parent\RoutineController::create', ['as' => 'parent.routines.create']);
    $routes->get('routines/(:num)/edit', 'Parent\RoutineController::edit/$1', ['as' => 'parent.routines.edit']);
    $routes->post('routines/(:num)', 'Parent\RoutineController::update/$1', ['as' => 'parent.routines.update']);
    $routes->post('routines/(:num)/delete', 'Parent\RoutineController::delete/$1', ['as' => 'parent.routines.delete']);
    $routes->get('routines/(:num)/tasks/new', 'Parent\RoutineTaskController::new/$1', ['as' => 'parent.routine-tasks.new']);
    $routes->post('routines/(:num)/tasks', 'Parent\RoutineTaskController::create/$1', ['as' => 'parent.routine-tasks.create']);
    $routes->get('routine-tasks/(:num)/edit', 'Parent\RoutineTaskController::edit/$1', ['as' => 'parent.routine-tasks.edit']);
    $routes->post('routine-tasks/(:num)', 'Parent\RoutineTaskController::update/$1', ['as' => 'parent.routine-tasks.update']);
    $routes->post('routine-tasks/(:num)/delete', 'Parent\RoutineTaskController::delete/$1', ['as' => 'parent.routine-tasks.delete']);
    $routes->get('children/(:num)/devices', 'Parent\DeviceController::index/$1', ['as' => 'parent.child.devices']);
    $routes->post('children/(:num)/devices/setup', 'Parent\DeviceController::setup/$1', ['as' => 'parent.child.devices.setup']);
    $routes->post('children/(:num)/devices/reset', 'Parent\DeviceController::reset/$1', ['as' => 'parent.child.devices.reset']);
    $routes->post('children/(:num)/devices/(:num)/revoke', 'Parent\DeviceController::revoke/$1/$2', ['as' => 'parent.child.devices.revoke']);
    $routes->post('children/(:num)/devices/(:num)/delete', 'Parent\DeviceController::delete/$1/$2', ['as' => 'parent.child.devices.delete']);
    $routes->post('logout', 'Auth\LoginController::logout', ['as' => 'parent.logout']);
});
