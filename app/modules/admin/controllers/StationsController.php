<?php
namespace Modules\Admin\Controllers;

use \Entity\Station;
use \Entity\Station as Record;

class StationsController extends BaseController
{
    public function permissions()
    {
        return $this->acl->isAllowed('administer stations');
    }
    
    public function indexAction()
    {
        $this->view->stations = $this->em->createQuery('SELECT s FROM Entity\Station s ORDER BY s.name ASC')
            ->getArrayResult();
    }
    
    public function editAction()
    {
        $form = new \App\Form($this->current_module_config->forms->station);
        
        if ($this->hasParam('id'))
        {
            $id = (int)$this->getParam('id');
            $record = Record::find($id);
            $form->setDefaults($record->toArray(FALSE, TRUE));
        }

        if($_POST && $form->isValid($_POST) )
        {
            $data = $form->getValues();

            if (!($record instanceof Record))
                $record = new Record;

            $record->fromArray($data);
            $record->save();

            // Clear station cache.
            $cache = $this->di->get('cache');
            $cache->remove('stations');

            $this->alert('Changes saved.', 'green');
            return $this->redirectFromHere(array('action' => 'index', 'id' => NULL));
        }

        $this->renderForm($form, 'edit', 'Edit Record');
    }
    
    public function deleteAction()
    {
        $record = Record::find($this->getParam('id'));
        if ($record)
            $record->delete();
            
        $this->alert('Record deleted.', 'green');
        $this->redirectFromHere(array('action' => 'index', 'id' => NULL, 'csrf' => NULL));
    }
}