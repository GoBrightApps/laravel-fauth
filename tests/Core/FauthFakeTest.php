<?php

declare(strict_types=1);

use Bright\Fauth\FauthFake;
use Kreait\Firebase\Auth\UserRecord;

beforeEach(function () {
    $this->fake = new FauthFake;
});

it('creates users and finds them by id/email/phone (and records calls)', function () {
    $u1 = $this->fake->create(['uid' => 'u1', 'email' => 'a@ex.com', 'displayName' => 'Alpha', 'phoneNumber' => '+111']);
    $u2 = $this->fake->create(['uid' => 'u2', 'email' => 'b@ex.com', 'displayName' => 'Beta',  'phoneNumber' => '+222']);

    expect($u1)->toBeInstanceOf(UserRecord::class);
    expect($this->fake->find('u1')?->email)->toBe('a@ex.com');
    expect($this->fake->findByEmail('b@ex.com')?->uid)->toBe('u2');
    expect($this->fake->findByPhone('+111')?->uid)->toBe('u1');

    // negative lookups
    expect($this->fake->find('missing'))->toBeNull();
    expect($this->fake->findByEmail('none@ex.com'))->toBeNull();
    expect($this->fake->findByPhone('+000'))->toBeNull();

    // call log sanity
    $this->fake->assertCalled('create');
    $this->fake->assertCalled('find');
    $this->fake->assertCalled('findByEmail');
    $this->fake->assertCalled('findByPhone');
});

it('updates and upserts users', function () {
    $this->fake->create(['uid' => 'u5', 'email' => 'e@ex.com', 'displayName' => 'Early']);

    // update existing
    $updated = $this->fake->update('u5', ['displayName' => 'Edited']);
    expect($updated)->toBeInstanceOf(UserRecord::class)->and($updated->displayName)->toBe('Edited');

    // update again
    $updated2 = $this->fake->update('u5', ['email' => 'edited@ex.com']);
    expect($updated2?->email)->toBe('edited@ex.com');

    // update returns null when unknown/missing uid
    expect($this->fake->update('nope', ['x' => 'y']))->toBeNull();
    expect($this->fake->update(null, ['x' => 'y']))->toBeNull();

    // upsert: update path
    $up1 = $this->fake->upsert('u5', ['displayName' => 'Up1']);
    expect($up1?->displayName)->toBe('Up1');

    // upsert: create path
    $up2 = $this->fake->upsert(null, ['email' => 'new@ex.com']);
    expect($up2)->toBeInstanceOf(UserRecord::class)->and($up2->email)->toBe('new@ex.com');

    $this->fake->assertCalled('update');
    $this->fake->assertCalled('upsert');
});

it('deletes single & multiple users and deleteAllUsers returns count', function () {
    $this->fake->create(['uid' => 'u7']);
    $this->fake->create(['uid' => 'u8']);
    $this->fake->create(['uid' => 'u9']);

    // single delete
    expect($this->fake->delete('u8'))->toBeTrue();
    expect($this->fake->find('u8'))->toBeNull();

    // batch delete
    expect($this->fake->delete(['u7', 'u9']))->toBeTrue();
    expect($this->fake->findMany(['u7', 'u9']))->each->toBeNull();

    // idempotent
    expect($this->fake->delete('missing'))->toBeTrue();

    // repopulate and wipe all
    $this->fake->create(['uid' => 'a']);
    $this->fake->create(['uid' => 'b']);
    expect($this->fake->deleteAllUsers())->toBe(2);
    expect($this->fake->count())->toBe(0);

    $this->fake->assertCalled('delete');
    $this->fake->assertCalled('deleteAllUsers');
});

it('findMany/all/count and search work as expected', function () {
    $this->fake->create(['uid' => 'm1', 'email' => 'alpha@ex.com', 'displayName' => 'Alpha']);
    $this->fake->create(['uid' => 'm2', 'email' => 'beta@ex.com',  'displayName' => 'Beta']);
    $this->fake->create(['uid' => 'm3', 'email' => 'gamma@ex.com', 'displayName' => 'Gamma']);

    // findMany preserves order and fills nulls
    $many = $this->fake->findMany(['m2', 'missing', 'm1']);
    expect($many)->toHaveCount(3);
    expect($many[0]?->uid)->toBe('m2');
    expect($many[1])->toBeNull();
    expect($many[2]?->uid)->toBe('m1');

    // all & count
    $all = $this->fake->all();
    expect($all)->toHaveCount(3);
    expect($this->fake->count())->toBe(3);

    // search: empty => first page; filter by name/email; window by offset/limit
    $page = $this->fake->search('', 0, 2);
    expect($page)->toHaveCount(2);

    $alpha = $this->fake->search('alp', 0, 10);
    expect($alpha)->toHaveCount(1)->and($alpha->first()->uid)->toBe('m1');

    $page2 = $this->fake->search('', 1, 2);
    expect($page2)->toHaveCount(2);

    $this->fake->assertCalled('findMany');
    $this->fake->assertCalled('all');
    $this->fake->assertCalled('count');
    $this->fake->assertCalled('search');
});

it('message/reset/verify & auth-like helpers behave simply', function () {
    // message
    expect($this->fake->message('x', 'Default'))->toBe('Default');
    expect($this->fake->message('fallback'))->toBe('fallback');

    // reset link
    expect($this->fake->sendResetLink('me@ex.com'))->toBe('RESET_LINK_SENT');

    // verification email just records a call
    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        public $timestamps = false;

        protected $fillable = ['uid', 'email'];

        protected $attributes = ['uid' => 'v1', 'email' => 'v@ex.com'];
    };
    $this->fake->sendVerificationEmail(new $model);
    $this->fake->assertCalled('sendVerificationEmail');

    // check/attempt & enabled/disabled are intentionally simple
    expect($this->fake->check('a@ex.com', 'pass'))->toBeTrue();
    expect($this->fake->check('a@ex.com', ''))->toBeFalse();

    $this->fake->create(['uid' => 'aa', 'email' => 'aa@ex.com']);
    expect($this->fake->attempt(['email' => 'aa@ex.com', 'password' => 'x']))->toBeInstanceOf(UserRecord::class);
    expect($this->fake->attempt(['email' => 'no@ex.com', 'password' => 'x']))->toBeNull();

    expect($this->fake->enabled('aa')?->uid)->toBe('aa');
    expect($this->fake->disabled('aa')?->uid)->toBe('aa');

    $this->fake->assertCalled('message');
    $this->fake->assertCalled('sendResetLink');
    $this->fake->assertCalled('check');
    $this->fake->assertCalled('attempt');
    $this->fake->assertCalled('enabled');
    $this->fake->assertCalled('disabled');
});
