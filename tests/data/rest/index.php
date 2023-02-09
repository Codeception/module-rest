<?php

include_once __DIR__ . '/server.php';

$GLOBALS['RESTmap'] = [];

$GLOBALS['RESTmap']['GET'] = [
    'user' => fn(): array => [
        'name'    => 'davert',
        'email'   => 'davert@mail.ua',
        'aliases' => [
            'DavertMik',
            'davert.ua'
        ],
        'address' => [
            'city'    => 'Kyiv',
            'country' => 'Ukraine',
        ]],
    'zeroes' => fn(): array => [
        'responseCode' => 0,
        'message' => 'OK',
        'data' => [
            9,
            0,
            0
        ],
    ],
    'foo' => function() {
        if (isset($_SERVER['HTTP_FOO'])) {
            return 'foo: "' . $_SERVER['HTTP_FOO'] . '"';
        }

        return 'foo: not found';
    }

];

$GLOBALS['RESTmap']['POST'] = [
    'user' => function() {
        $name = $_POST['name'];
        return ['name' => $name];
    },
    'file-upload' => fn(): array => [
        'uploaded' => isset($_FILES['file']['tmp_name']) && file_exists($_FILES['file']['tmp_name']),
    ]
];

$GLOBALS['RESTmap']['PUT'] = [
    'user' => function() {
        $name = $_REQUEST['name'];
        $user = ['name' => 'davert', 'email' => 'davert@mail.ua'];
        $user['name'] = $name;
        return $user;
    }
];

$GLOBALS['RESTmap']['DELETE'] = [
    'user' => function(): void {
        header('error', false, 404);
    }
];

RESTServer();
