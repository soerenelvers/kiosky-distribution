<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

use Kiosky\Core\Api;
use Kiosky\Core\CrewBrainService;
use Kiosky\Core\Crypto;
use Kiosky\Core\DwdService;
use Kiosky\Core\EasyJobService;
use Kiosky\Kiosky\Persistence\Typo3StateStore;

final readonly class ApiFactory
{
    public function __construct(
        private Typo3StateStore $store,
        private Typo3HttpClient $http,
    ) {
    }

    /** @param array{id:string,name:string,email:string,role:string} $identity */
    public function create(array $identity): Api
    {
        $secret = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
        $crypto = new Crypto($secret);
        return new Api(
            $this->store,
            $identity,
            '2.4.10',
            new CrewBrainService($this->http, $crypto),
            new DwdService($this->http),
            new EasyJobService($this->http, $crypto),
        );
    }
}
