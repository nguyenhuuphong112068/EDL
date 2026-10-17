<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Chưa đăng nhập thì mọi trang nghiệp vụ chuyển về trang đăng nhập. */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/documents')->assertRedirect(route('login'));
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Đăng nhập')
            ->assertSee('name="userName"', false)
            ->assertSee('name="password"', false);
    }
}
