<?php

use Flarum\Database\Migration;

return Migration::addColumns('posts', [
    'reply_to' => ['integer'],
    'reply_count' => ['integer']
]);
