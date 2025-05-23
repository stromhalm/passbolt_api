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
 * @since         3.0.0
 */

namespace Passbolt\ResourceTypes\Controller;

use App\Controller\AppController;
use App\Utility\Application\FeaturePluginAwareTrait;
use Cake\Core\Configure;
use Passbolt\ResourceTypes\Model\Entity\ResourceType;
use Passbolt\ResourceTypes\Service\ResourceTypesFinderInterface;

class ResourceTypesIndexController extends AppController
{
    use FeaturePluginAwareTrait;

    /**
     * Resource Types Index action
     *
     * @param \Passbolt\ResourceTypes\Service\ResourceTypesFinderInterface $resourceTypesFinder Resource types finder service.
     * @throws \Cake\Http\Exception\NotFoundException if plugin is disabled by admin
     * @return void
     */
    public function index(ResourceTypesFinderInterface $resourceTypesFinder)
    {
        $this->assertJson();
        $resourceTypes = $resourceTypesFinder->find();
        if (Configure::read('passbolt.v5.enabled')) {
            $options = $this->QueryString->get([
                'contain' => ['resources_count'],
                'filter' => ['is-deleted'],
            ]);
            $resourceTypesFinder->filter($resourceTypes, $options);
            if ($this->User->isAdmin()) {
                $resourceTypesFinder->contain($resourceTypes, $options);
            }
        } else {
            $resourceTypes = $resourceTypes
                ->find('notDeleted')
                ->where(['slug NOT IN' => ResourceType::V5_RESOURCE_TYPE_SLUGS]);
        }

        $this->success(__('The operation was successful.'), $resourceTypes->all());
    }
}
