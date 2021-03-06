<?php
namespace Modules\Stations\Controllers;

use \Entity\Station;

class ProfileController extends BaseController
{
    protected function _getForm()
    {
        $base_form = $this->module_config['admin']->forms->station->toArray();
        unset($base_form['groups']['admin']);

        return new \App\Form($base_form);
    }

    public function indexAction()
    {
        $form = $this->_getForm();
        $form->populate($this->station->toArray());

        $this->view->form = $form;
    }

    public function editAction()
    {
        $form = $this->_getForm();

        $form->setDefaults($this->station->toArray());

        if(!empty($_POST) && $form->isValid($_POST))
        {
            $data = $form->getValues();

            /*
            $files = $form->processFiles('stations');
            foreach($files as $file_field => $file_paths)
                $data[$file_field] = $file_paths[1];
            */

            $this->station->fromArray($data);
            $this->station->save();

            // Clear station cache.
            $cache = $this->di->get('cache');
            $cache->remove('stations');

            return $this->redirectFromHere(array('action' => 'index'));
        }

        return $this->renderForm($form, 'edit', 'Edit Station Profile');
    }
}
