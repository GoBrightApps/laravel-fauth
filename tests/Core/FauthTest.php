<?php

declare(strict_types=1);

use Bright\Fauth\FauthFake;
use Kreait\Firebase\Auth\UserRecord;

beforeEach(function () {
    $this->fauth = new FauthFake;
});

/**
 * Small helper for brevity
 */
function makeUser(FauthFake $fake, array $data): UserRecord
{
    return $fake::userRecord($data['uid'] ?? uniqid('u_'), $data);
}

it('create adds users and can be read via find/findByEmail/findByPhone', function () {
    $a = $this->fauth->create(['uid' => 'u1', 'email' => 'a@ex.com', 'displayName' => 'Alpha', 'phoneNumber' => '+111']);
    $b = $this->fauth->create(['uid' => 'u2', 'email' => 'b@ex.com', 'displayName' => 'Beta',  'phoneNumber' => '+222']);

    expect($a)->toBeInstanceOf(UserRecord::class);
    expect($this->fauth->find('u1')?->email)->toBe('a@ex.com');
    expect($this->fauth->findByEmail('b@ex.com')?->uid)->toBe('u2');
    expect($this->fauth->findByPhone('+111')?->uid)->toBe('u1');

    // negative
    expect($this->fauth->find('missing'))->toBeNull();
    expect($this->fauth->findByEmail('none@ex.com'))->toBeNull();
    expect($this->fauth->findByPhone('+000'))->toBeNull();
});

it('check returns true only for non-empty email & password', function () {
    expect($this->fauth->check('a@ex.com', 'x'))->toBeTrue();
    expect($this->fauth->check('a@ex.com', ''))->toBeFalse();
});

it('attempt returns a user for email present, null otherwise', function () {
    $this->fauth->create(['uid' => 'u3', 'email' => 'c@ex.com']);

    $ok = $this->fauth->attempt(['email' => 'c@ex.com', 'password' => 'any']);
    expect($ok)->toBeInstanceOf(UserRecord::class)->and($ok->email)->toBe('c@ex.com');

    expect($this->fauth->attempt(['email' => 'no@ex.com', 'password' => 'any']))->toBeNull();
    expect($this->fauth->attempt(['email' => 'only']))->toBeNull();
});

it('disabled/enabled simply mirror find()', function () {
    $this->fauth->create(['uid' => 'u4', 'email' => 'd@ex.com']);

    expect($this->fauth->disabled('u4')?->uid)->toBe('u4');
    expect($this->fauth->enabled('u4')?->uid)->toBe('u4');

    expect($this->fauth->disabled('missing'))->toBeNull();
    expect($this->fauth->enabled('missing'))->toBeNull();
});

it('update returns null for missing uid or unknown user, otherwise merges data', function () {
    // missing uid
    expect($this->fauth->update(null, ['email' => 'x@ex.com']))->toBeNull();

    // unknown uid
    expect($this->fauth->update('nope', ['email' => 'x@ex.com']))->toBeNull();

    // happy path
    $this->fauth->create(['uid' => 'u5', 'email' => 'e@ex.com', 'displayName' => 'Early']);
    $updated = $this->fauth->update('u5', ['displayName' => 'Edited']);
    expect($updated)->toBeInstanceOf(UserRecord::class)->and($updated->displayName)->toBe('Edited');

    // update again
    $updated2 = $this->fauth->update('u5', ['email' => 'edited@ex.com']);
    expect($updated2?->email)->toBe('edited@ex.com');
});

it('upsert updates when exists and creates when not', function () {
    $this->fauth->create(['uid' => 'u6', 'email' => 'f@ex.com']);

    $u = $this->fauth->upsert('u6', ['displayName' => 'F-Name']);
    expect($u?->displayName)->toBe('F-Name');

    $c = $this->fauth->upsert(null, ['email' => 'new@ex.com']);
    expect($c)->toBeInstanceOf(UserRecord::class)->and($c->email)->toBe('new@ex.com');
});

it('delete supports single and array ids and always returns true', function () {
    $this->fauth->create(['uid' => 'u7']);
    $this->fauth->create(['uid' => 'u8']);
    $this->fauth->create(['uid' => 'u9']);

    expect($this->fauth->delete('u8'))->toBeTrue();
    expect($this->fauth->find('u8'))->toBeNull();

    expect($this->fauth->delete(['u7', 'u9']))->toBeTrue();
    expect($this->fauth->findMany(['u7', 'u9']))->each->toBeNull();

    // idempotent
    expect($this->fauth->delete('u-missing'))->toBeTrue();
});

it('deleteAllUsers clears all and returns removed count', function () {
    $this->fauth->create(['uid' => 'a']);
    $this->fauth->create(['uid' => 'b']);
    $this->fauth->create(['uid' => 'c']);

    expect($this->fauth->count())->toBe(3);
    expect($this->fauth->deleteAllUsers())->toBe(3);
    expect($this->fauth->count())->toBe(0);
});

it('updatePassword returns existing user or creates new if not exists', function () {
    $this->fauth->create(['uid' => 'pw1', 'email' => 'pw@ex.com']);
    $same = $this->fauth->updatePassword('pw@ex.com', 'secret');
    expect($same?->uid)->toBe('pw1');

    // creates when missing
    $created = $this->fauth->updatePassword('new@ex.com', 'secret');
    expect($created)->toBeInstanceOf(UserRecord::class)->and($created->email)->toBe('new@ex.com');
});

it('findMany returns collection in given order with nulls for missing; empty input yields empty', function () {
    $this->fauth->create(['uid' => 'm1']);
    $this->fauth->create(['uid' => 'm2']);

    $many = $this->fauth->findMany(['m2', 'missing', 'm1']);
    expect($many)->toHaveCount(3);
    expect($many[0]?->uid)->toBe('m2');
    expect($many[1])->toBeNull();
    expect($many[2]?->uid)->toBe('m1');

    // empty input
    expect($this->fauth->findMany([]))->toHaveCount(0);

    // second call (no caching but ensure stability)
    $again = $this->fauth->findMany(['m1', 'm2']);
    expect($again)->toHaveCount(2);
});

it('all returns all current users', function () {
    $this->fauth->create(['uid' => 'a1']);
    $this->fauth->create(['uid' => 'a2']);
    $this->fauth->create(['uid' => 'a3']);

    $all = $this->fauth->all();
    expect($all)->toHaveCount(3)->and($all->pluck('uid')->all())->toBe(['a1', 'a2', 'a3']);
});

it('query returns all (fake behavior) and can be called twice', function () {
    $this->fauth->create(['uid' => 'q1']);
    $this->fauth->create(['uid' => 'q2']);

    $first = $this->fauth->query(['limit' => 10]);
    $second = $this->fauth->query(['limit' => 10]);

    expect($first)->toHaveCount(2)->and($second)->toHaveCount(2);
});

it('count returns current number of users', function () {
    expect($this->fauth->count())->toBe(0);
    $this->fauth->create(['uid' => 'c1']);
    $this->fauth->create(['uid' => 'c2']);
    expect($this->fauth->count())->toBe(2);
});

it('search filters by displayName and email and supports offset/limit', function () {
    $this->fauth->create(['uid' => 's1', 'displayName' => 'Alpha', 'email' => 'alpha@ex.com']);
    $this->fauth->create(['uid' => 's2', 'displayName' => 'Beta',  'email' => 'beta@ex.com']);
    $this->fauth->create(['uid' => 's3', 'displayName' => 'Gamma', 'email' => 'gamma@ex.com']);

    // empty term => all (limited)
    $all = $this->fauth->search('', 0, 2);
    expect($all)->toHaveCount(2);

    // term match
    $alpha = $this->fauth->search('alp', 0, 10);
    expect($alpha)->toHaveCount(1)->and($alpha->first()->uid)->toBe('s1');

    // offset/limit window
    $page2 = $this->fauth->search('', 1, 2);
    expect($page2)->toHaveCount(2);
});

it('message returns default or code as-is', function () {
    expect($this->fauth->message('x', 'Default'))->toBe('Default');
    expect($this->fauth->message('fallback'))->toBe('fallback');
});

it('sendResetLink returns a success string', function () {
    expect($this->fauth->sendResetLink('me@ex.com'))->toBe('RESET_LINK_SENT');
});

it('sendVerificationEmail is a no-op but records the call', function () {
    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        public $timestamps = false;

        protected $fillable = ['uid', 'email'];

        protected $attributes = ['uid' => 'v1', 'email' => 'v@ex.com'];
    };

    $returned = $this->fauth->sendVerificationEmail(new $model);
    // ensure the call was tracked
    $this->fauth->assertCalled('sendVerificationEmail');

    expect($returned)->toBeNull();

});

it('records all method calls and can assertCalled / throws on missing', function () {
    // Call a few methods
    $this->fauth->create(['uid' => 'r1']);
    $this->fauth->find('r1');
    $this->fauth->findMany(['r1']);

    // calls() returns chronological log
    $calls = $this->fauth->calls();
    expect($calls)->toBeArray()->and(count($calls))->toBeGreaterThanOrEqual(3);
    expect(collect($calls)->pluck('method')->all())->toContain('create', 'find', 'findMany');

    // assertCalled passes for called method
    $this->fauth->assertCalled('find');

    // assertCalled throws for non-called method
    $this->fauth->assertCalled('nonExistingMethod');
})->throws(RuntimeException::class);
