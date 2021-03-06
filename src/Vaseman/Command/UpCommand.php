<?php
/**
 * Part of vaseman project. 
 *
 * @copyright  Copyright (C) 2014 {ORGANIZATION}. All rights reserved.
 * @license    GNU General Public License version 2 or later;
 */

namespace Vaseman\Command;

use Vaseman\Asset\Asset;
use Vaseman\Processor\AbstractFileProcessor;
use Windwalker\Console\Command\Command;
use Windwalker\Core\DateTime\DateTime;
use Windwalker\Event\Event;
use Windwalker\Filesystem\Filesystem;
use Windwalker\Filesystem\Folder;
use Windwalker\Filesystem\Path;
use Windwalker\IO\Input;
use Windwalker\Ioc;
use Windwalker\Web\Application;

/**
 * The UpCommand class.
 * 
 * @since  {DEPLOY_VERSION}
 */
class UpCommand extends Command
{
	/**
	 * Property isEnabled.
	 *
	 * @var  boolean
	 */
	public static $isEnabled = true;

	/**
	 * Property name.
	 *
	 * @var  string
	 */
	protected $name = 'up';

	/**
	 * Property description.
	 *
	 * @var  string
	 */
	protected $description = 'Generate site.';

	/**
	 * Property usage.
	 *
	 * @var  string
	 */
	protected $usage = 'up <cmd><command></cmd> <option>[options]</option>';

	/**
	 * initialise
	 *
	 * @return  void
	 */
	protected function initialise()
	{
		$this->addOption('d')
			->alias('dir')
			->description('Directory to convert.');
	}

	/**
	 * doExecute
	 *
	 * @return  integer
	 */
	protected function doExecute()
	{
		DateTime::setDefaultTimezone();

		$this->out()->out('Vaseman generator')
			->out('-----------------------------')->out()
			->out('<comment>Start generating site</comment>')->out();

		$dataRoot = $this->console->get('project.path.data', WINDWALKER_ROOT);
		$folders = $this->console->get('folders', array());

		$profile = Ioc::getProfile();

		Ioc::setProfile('web');

		/** @var Application $app */
		$app = new Application;
		$app->set('outer_project', $this->console->get('outer_project'));
		$app->boot();
		$app->getRouter();

		$package = $app->getPackage('vaseman');

		$container = $app->getContainer();

		$container->share('current.package', $package);

		$controller = $package->getController('Page/GetController');

		$event = new Event('onBeforeRenderFiles');
		$event['config'] = $this->console->getConfig();
		$event['controller'] = $controller;
		$event['io'] = $this->io;

		Ioc::getDispatcher()->triggerEvent($event);

		$assets = array();
		$processors = array();

		foreach ($folders as $folder)
		{
			$files = Filesystem::files($dataRoot . '/' . $folder, true);

			foreach ($files as $file)
			{
				$this->out('[<option>Rendering file</option>]: ' . $file);

				$asset = new Asset($file, $dataRoot . '/' . $folder);

				$layout = Path::clean($asset->getPath(), '/');

				$input = new Input(array('paths' => explode('/', $layout)));

				$config = $controller->getConfig();
				$config->set('layout.path', $asset->getRoot());
				$config->set('layout.folder', $folder);

				$controller->setInput($input)->execute();

				$processors[] = $controller->getProcessor();
			}
		}

		$event->setName('onAfterRenderFiles');
		$event['processors'] = $processors;
		Ioc::getDispatcher()->triggerEvent($event);

		$event->setName('onBeforeWriteFiles');
		Ioc::getDispatcher()->triggerEvent($event);

		$dir = $this->getOption('dir');

		$dir = $dir ? : $this->console->get('outer_project') ? "" : 'output';

		$dir = $this->console->get('project.path.root') . '/' . $dir;

		/** @var AbstractFileProcessor $processor */
		foreach ($processors as $processor)
		{
			$file = Path::clean($dir . '/' . $processor->getTarget());

			$this->out('[<info>Write file</info>]: ' . $file);

			Folder::create(dirname($file));

			file_put_contents($file, $processor->getOutput());
		}

		$event->setName('onAfterWriteFiles');
		Ioc::getDispatcher()->triggerEvent($event);

		$this->out()->out('<info>Complete</info>')->out();

		return 0;
	}
}
