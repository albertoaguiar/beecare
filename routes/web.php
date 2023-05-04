<?php

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

Route::get('/', function () {
    return view('index');
});
Route::get('/insert-client', 'App\Http\Controllers\ClientController@insertClient');
Route::get('/fill-form', 'App\Http\Controllers\ClientController@fillForm');
Route::get('/download-file', 'App\Http\Controllers\ClientController@downloadFile');
Route::get('/upload-file', 'App\Http\Controllers\ClientController@uploadFile');

Route::get('/read-pdf', 'App\Http\Controllers\PdfController@convertPdfToCsv');
Route::get('/read-pdf2', 'App\Http\Controllers\Pdf2Controller@convertPdfToCsv');