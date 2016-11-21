<?php

Route::group(['before' => 'auth'], function() {
    Route::any('ueditor/controller', 'UeditorController@getAction');
});
