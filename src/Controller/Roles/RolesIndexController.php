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
namespace App\Controller\Roles;

use App\Controller\AppController;
use App\Model\Table\RolesTable;

/**
 * RolesIndexController Class
 */
class RolesIndexController extends AppController
{
    /**
     * @var \App\Model\Table\RolesTable
     */
    protected RolesTable $Roles;

    /**
     * Roles Index action
     *
     * @return void
     */
    public function index()
    {
        $this->assertJson();

        $this->Roles = $this->fetchTable('Roles');
        $roles = $this->Roles->find('all');
        $this->success(__('The operation was successful.'), $roles);
    }
}
