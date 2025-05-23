<?php
declare(strict_types=1);

/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SA (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SA (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         3.9.0
 */
namespace Passbolt\MultiFactorAuthentication\Test\Scenario\Multi;

use CakephpFixtureFactories\Scenario\FixtureScenarioInterface;
use Passbolt\MultiFactorAuthentication\Test\Factory\MfaOrganizationSettingFactory;
use Passbolt\MultiFactorAuthentication\Utility\MfaSettings;

/**
 * MfaTotpDuoOrganizationOnlyScenario
 */
class MfaTotpDuoOrganizationOnlyScenario implements FixtureScenarioInterface
{
    public function load(...$args): array
    {
        $isSupported = $args[0] ?? true;
        $orgSetting = MfaOrganizationSettingFactory::make()
            ->setProviders(MfaSettings::PROVIDER_DUO, $isSupported)
            ->duoWithTotp()
            ->persist();

        return [$orgSetting];
    }
}
