<?php

test('the welcome url redirects to the member home', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/');
});
