<?php

use App\Core\Router;

/** @var Router $router */

// Public routes
$router->get('/', 'HomeController@index');
$router->get('/home', 'HomeController@index');

// Auth routes
$router->both('/auth/login', 'AuthController@login');
$router->both('/auth/register', 'AuthController@register');
$router->get('/auth/logout', 'AuthController@logout');

// Products
$router->get('/product', 'ProductController@index');
$router->get('/product/view/{id}', 'ProductController@view');
$router->both('/product/add', 'ProductController@add', ['auth']);
$router->both('/product/edit/{id}', 'ProductController@edit', ['auth']);
$router->post('/product/delete/{id}', 'ProductController@delete', ['auth']);

// Forum
$router->get('/forum', 'ForumController@index');
$router->get('/forum/category/{id}', 'ForumController@category');
$router->get('/forum/topic/{id}', 'ForumController@topic');
$router->both('/forum/new_category', 'ForumController@new_category', ['auth', 'admin']);
$router->both('/forum/new_topic/{category_id}', 'ForumController@new_topic', ['auth']);
$router->post('/forum/reply/{topic_id}', 'ForumController@reply', ['auth']);

// Messages
$router->get('/message', 'MessageController@index', ['auth']);
$router->get('/message/conversation/{user_id}', 'MessageController@index', ['auth']);
$router->post('/message/send', 'MessageController@send', ['auth']);

// Profile
$router->get('/profile', 'ProfileController@index', ['auth']);
$router->get('/profile/view/{id}', 'ProfileController@view');
$router->both('/profile/edit', 'ProfileController@edit', ['auth']);
$router->post('/profile/delete', 'ProfileController@delete', ['auth']);
$router->get('/profile/products/{id}', 'ProfileController@products');
$router->get('/profile/topics/{id}', 'ProfileController@topics');

// Dashboard
$router->get('/dashboard', 'DashboardController@index', ['auth']);

// Admin
$router->get('/admin', 'AdminController@index', ['auth', 'admin']);
$router->get('/admin/products', 'AdminController@products', ['auth', 'admin']);
$router->post('/admin/products/approve/{id}', 'AdminController@approveProduct', ['auth', 'admin']);
$router->post('/admin/products/reject/{id}', 'AdminController@rejectProduct', ['auth', 'admin']);
$router->post('/admin/products/delete/{id}', 'AdminController@deleteProduct', ['auth', 'admin']);
$router->get('/admin/users', 'AdminController@users', ['auth', 'admin']);
$router->post('/admin/users/toggle-admin/{id}', 'AdminController@toggleAdmin', ['auth', 'admin']);
$router->post('/admin/users/delete/{id}', 'AdminController@deleteUser', ['auth', 'admin']);
