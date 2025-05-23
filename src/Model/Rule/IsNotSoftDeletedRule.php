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
 * @since         2.0.0
 */

namespace App\Model\Rule;

use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Exception;

class IsNotSoftDeletedRule
{
    /**
     * Performs the check
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to check
     * @param array $options Options passed to the check
     * @return bool
     */
    public function __invoke(EntityInterface $entity, array $options): bool
    {
        if (!isset($options['errorField']) || !isset($options['table'])) {
            return false;
        }

        try {
            $Table = TableRegistry::getTableLocator()->get($options['table']);
            $id = $entity->get($options['errorField']);
            $lookupEntity = $Table->get($id);
            $deleted = $lookupEntity->get('deleted');
            if ($deleted instanceof DateTime) {
                return $deleted->isFuture();
            }

            return $lookupEntity->get('deleted') !== true;
        } catch (Exception $e) {
        }

        return false;
    }
}
