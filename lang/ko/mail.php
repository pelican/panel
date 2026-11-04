<?php

return [
    'greeting' => ':name 님, 안녕하세요.',

    'account_created' => [
        'body' => ':app 에서 새로운 계정을 생성하셨습니다.',
        'username' => '사용자명: :username',
        'email' => '이메일: :email',
        'action' => '계정 설정하기',
    ],

    'added_to_server' => [
        'body' => '다음 서버에 하위 사용자로써 등록되셨습니다.',
        'server_name' => '서버 이름: :name',
        'action' => '서버 접속',
    ],

    'removed_from_server' => [
        'body' => '다음 서버의 하위 사용자 권한이 회수되셨습니다.',
        'server_name' => '서버 이름: :name',
        'action' => '패널로 가기',
    ],

    'server_installed' => [
        'body' => '서버 설치가 완료되었으며 이제 사용하실 수 있습니다.',
        'server_name' => '서버 이름: :name',
        'action' => '로그인 및 서버 사용',
    ],

    'backup_completed' => [
        'body_success' => '백업이 완료되었습니다.',
        'body_failed' => '백업이 실패하였습니다.',
        'backup_name' => '백업 이름: :name',
        'server_name' => '서버 이름: :name',
        'action' => '백업 보기',
    ],

    'mail_tested' => [
        'subject' => '패널 테스트 메시지',
        'body' => '이 메일은 패널 메일 시스템의 테스트입니다. 이 메일을 보셨다면 메일 기능이 정상 작동하는 것입니다.',
    ],
];
