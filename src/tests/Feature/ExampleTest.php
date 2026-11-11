<?php

test('the application redirects the root url to the admin panel', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
