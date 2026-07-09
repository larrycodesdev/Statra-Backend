<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/test/avatar-upload', function () {
//     return view('test.avatar-upload');
// });

// Route::get('/email-testing', function () {
//     try {
//         Mail::raw('Test email from Statra Backend. If you received this, mail is configured correctly.', function ($message) {
//             $message->to('larrycodesdev@gmail.com')
//                     ->subject('Statra Mail Test');
//         });

//         return response()->json([
//             'success' => true,
//             'message' => 'Email sent successfully to larrycodesdev@gmail.com',
//         ]);
//     } catch (\Throwable $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Mail failed: ' . $e->getMessage(),
//         ]);
//     }
// });

