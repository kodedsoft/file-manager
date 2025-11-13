<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('upload.{uploadId}', function ($user, $uploadId) {
    // allow only the owner or admin
    return \App\Models\CsvUpload::where('id', $uploadId)->exists();
});
