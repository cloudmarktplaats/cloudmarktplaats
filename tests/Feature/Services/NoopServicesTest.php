<?php

declare(strict_types=1);

use App\Services\Dac7\Dac7Service;
use App\Services\Reputation\ReputationService;
use App\Services\Web3\Web3Service;

it('Web3Service returns the documented sentinel values', function () {
    $svc = new Web3Service;
    expect($svc->getEscrowStatus(1))->toBeNull()
        ->and($svc->isEscrowEnabled())->toBeFalse();
});

it('Dac7Service returns zero / non-reportable until the real reporter ships', function () {
    $svc = new Dac7Service;
    expect($svc->getThresholdProgress(1))->toBe(0)
        ->and($svc->isReportable(1))->toBeFalse();
});

it('ReputationService returns null / 0 until reviews ship', function () {
    $svc = new ReputationService;
    expect($svc->getRating(1))->toBeNull()
        ->and($svc->getReviewCount(1))->toBe(0);
});
