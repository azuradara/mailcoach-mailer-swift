<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Dara\MailcoachMailerSwift\Exceptions\NoHostSet;
use Dara\MailcoachMailerSwift\MailcoachSwiftTransport;

it('can send an email', function () {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([
        new Response(204, []),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $transport = new MailcoachSwiftTransport('fake-api-token', 'domain.mailcoach.app', ['handler' => $handlerStack]);

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain')
        ->addPart('The html content', 'text/html');

    $transport->send($message);

    expect(count($container))->toBe(1);
    $request = $container[0]['request'];
    expect($request->getMethod())->toBe('POST');
    expect((string)$request->getUri())->toBe('https://domain.mailcoach.app/api/transactional-mails/send');

    $body = json_decode($request->getBody(), true);
    expect($body['from'])->toBe('From name <from@example.com>');
    expect($body['to'])->toBe('To name <to@example.com>');
    expect($body['subject'])->toBe('My subject');
    expect($body['text'])->toBe('The text content');
    expect($body['html'])->toBe('The html content');
});

it('can send a plaintext email', function () {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([
        new Response(204, []),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $transport = new MailcoachSwiftTransport('fake-api-token', 'domain.mailcoach.app', ['handler' => $handlerStack]);

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain');

    $transport->send($message);

    expect(count($container))->toBe(1);
    $request = $container[0]['request'];

    $body = json_decode($request->getBody(), true);
    expect($body['text'])->toBe('The text content');
    expect($body['html'])->toBeNull();
});

it('can process the transactional mail header', function () {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([
        new Response(204, []),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $transport = new MailcoachSwiftTransport('fake-api-token', 'domain.mailcoach.app', ['handler' => $handlerStack]);

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain');

    $message->getHeaders()->addTextHeader('X-Mailcoach-Transactional-Mail', 'my_template');

    $transport->send($message);

    expect(count($container))->toBe(1);
    $request = $container[0]['request'];

    $body = json_decode($request->getBody(), true);
    expect($body['mail_name'])->toBe('my_template');
});

it('can process the mailer header', function () {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([
        new Response(204, []),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $transport = new MailcoachSwiftTransport('fake-api-token', 'domain.mailcoach.app', ['handler' => $handlerStack]);

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain');

    $message->getHeaders()->addTextHeader('X-Mailcoach-Mailer', 'transactional-mailer');

    $transport->send($message);

    expect(count($container))->toBe(1);
    $request = $container[0]['request'];

    $body = json_decode($request->getBody(), true);
    expect($body['mailer'])->toBe('transactional-mailer');
});

it('can process the fake header', function () {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([
        new Response(204, []),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $transport = new MailcoachSwiftTransport('fake-api-token', 'domain.mailcoach.app', ['handler' => $handlerStack]);

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain');

    $message->getHeaders()->addTextHeader('X-Mailcoach-Fake', '1');

    $transport->send($message);

    expect(count($container))->toBe(1);
    $request = $container[0]['request'];

    $body = json_decode($request->getBody(), true);
    expect($body['fake'])->toBe('1');
});

it('throws when trying to define it twice', function () {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([
        new Response(204, []),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $transport = new MailcoachSwiftTransport('fake-api-token', 'domain.mailcoach.app', ['handler' => $handlerStack]);

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain');

    $message->getHeaders()->addTextHeader('X-Mailcoach-Transactional-Mail', 'my_template');
    $message->getHeaders()->addTextHeader('X-Mailcoach-Transactional-Mail', 'another_template');

    expect(fn() => $transport->send($message))->toThrow('Mailcoach only allows a single transactional mail to be defined.');
});

it('can pass through replacements', function () {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([
        new Response(204, []),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $transport = new MailcoachSwiftTransport('fake-api-token', 'domain.mailcoach.app', ['handler' => $handlerStack]);

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain');

    $message->getHeaders()->addTextHeader('X-Mailcoach-Replacement-first_name', json_encode('John'));
    $message->getHeaders()->addTextHeader('X-Mailcoach-Replacement-last_name', json_encode('Doe'));
    $message->getHeaders()->addTextHeader('X-Mailcoach-Replacement-array', json_encode(['foo', 'bar']));

    $transport->send($message);

    expect(count($container))->toBe(1);
    $request = $container[0]['request'];

    $body = json_decode($request->getBody(), true);
    expect($body['replacements']['first_name'])->toBe('John');
    expect($body['replacements']['last_name'])->toBe('Doe');
    expect($body['replacements']['array'])->toBe(['foo', 'bar']);
});

it('will throw an exception if the host is not set', function () {
    $transport = new MailcoachSwiftTransport('fake-api-token');

    $message = (new Swift_Message('My subject'))
        ->setFrom(['from@example.com' => 'From name'])
        ->setTo(['to@example.com' => 'To name'])
        ->setBody('The text content', 'text/plain');

    $transport->send($message);
})->throws(NoHostSet::class);
