<?php
/**
 * @copyright 2021 T-Systems International
 *
 * @author Bernd Rederlechner <bernd.rederlechner@t-systems.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\NextMagentaCloudSlup\Controller;

use OCP\AppFramework\Http\Response;

class SoapResponse extends Response {

	/**
	 * constructor of JSONResponse
	 * @param Xml message that satisfies SOAP WSDL (this is not enforced)
	 * @param int $statusCode the Http status code, defaults to 200
	 * @since 6.0.0
	 */
	public function __construct(string $message) {
		parent::__construct();

		header_remove();
		//$this->addHeader('Content-Type', 'text/xml; charset=utf-8');

		$this->message = $message;
	}

	public function render() {
		return $this->message;
	}
}
