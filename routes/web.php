<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function (\App\Repositories\UserRepositoryEloquent $userRepository) {
    $userRepository->sync(1, 'posts', Post::all());

    dd($userRepository->find(1)->posts()->delete());
});
