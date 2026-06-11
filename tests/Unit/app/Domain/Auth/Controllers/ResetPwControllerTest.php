<?php

namespace Unit\app\Domain\Auth\Controllers;

use Leantime\Core\Http\IncomingRequest;
use Leantime\Core\Language;
use Leantime\Core\UI\Template;
use Leantime\Domain\Auth\Controllers\ResetPw;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Unit\TestCase;

class ResetPwControllerTest extends TestCase
{
    use \Codeception\Test\Feature\Stub;

    public function test_password_help_request_does_not_send_email(): void
    {
        $authService = $this->make(AuthService::class, [
            'generateLinkAndSendEmail' => function (): void {
                $this->fail('忘记密码流程不应调用邮件发送服务');
            },
        ]);

        $_POST = [
            'resetPassword' => '1',
            'username' => 'student@example.com',
        ];

        app()->instance(AuthService::class, $authService);
        $controller = new ResetPw(
            $this->make(IncomingRequest::class),
            $this->make(Template::class, [
                'setNotification' => fn () => null,
            ]),
            $this->make(Language::class, [
                '__' => fn (string $key) => $key,
            ]),
        );
        $response = $controller->post([]);

        $this->assertSame(303, $response->getStatusCode());
    }
}
