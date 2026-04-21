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
$router->both('/product/add', 'ProductController@add', ['auth', 'legal']);
$router->both('/product/edit/{id}', 'ProductController@edit', ['auth', 'legal']);
$router->post('/product/delete/{id}', 'ProductController@delete', ['auth', 'legal']);

// Forum
$router->get('/forum', 'ForumController@index');
$router->get('/forum/category/{id}', 'ForumController@category');
$router->get('/forum/topic/{id}', 'ForumController@topic');
$router->both('/forum/new_category', 'ForumController@new_category', ['auth', 'admin', 'legal']);
$router->both('/forum/new_topic/{category_id}', 'ForumController@new_topic', ['auth', 'legal']);
$router->post('/forum/reply/{topic_id}', 'ForumController@reply', ['auth', 'legal']);

// Messages
$router->get('/message', 'MessageController@index', ['auth', 'legal']);
$router->get('/message/conversation/{user_id}', 'MessageController@index', ['auth', 'legal']);
$router->post('/message/send', 'MessageController@send', ['auth', 'legal']);

// Profile
$router->get('/profile', 'ProfileController@index', ['auth', 'legal']);
$router->get('/profile/view/{id}', 'ProfileController@view');
$router->both('/profile/edit', 'ProfileController@edit', ['auth', 'legal']);
$router->post('/profile/delete', 'ProfileController@delete', ['auth', 'legal']);
$router->get('/profile/products/{id}', 'ProfileController@products');
$router->get('/profile/topics/{id}', 'ProfileController@topics');

// Dashboard
$router->get('/dashboard', 'DashboardController@index', ['auth', 'legal']);

// Admin
$router->get('/admin', 'AdminController@index', ['auth', 'admin', 'legal']);
$router->get('/admin/products', 'AdminController@products', ['auth', 'admin', 'legal']);
$router->post('/admin/products/approve/{id}', 'AdminController@approveProduct', ['auth', 'admin', 'legal']);
$router->post('/admin/products/reject/{id}', 'AdminController@rejectProduct', ['auth', 'admin', 'legal']);
$router->post('/admin/products/delete/{id}', 'AdminController@deleteProduct', ['auth', 'admin', 'legal']);
$router->get('/admin/users', 'AdminController@users', ['auth', 'admin', 'legal']);
$router->post('/admin/users/toggle-admin/{id}', 'AdminController@toggleAdmin', ['auth', 'admin', 'legal']);
$router->post('/admin/users/delete/{id}', 'AdminController@deleteUser', ['auth', 'admin', 'legal']);

// Legal
$router->get('/legal/tos', 'LegalController@tos');
$router->get('/legal/privacy', 'LegalController@privacy');
$router->get('/legal/accept', 'LegalController@showAccept', ['auth']);
$router->post('/legal/accept', 'LegalController@accept', ['auth']);
