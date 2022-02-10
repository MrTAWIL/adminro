<?php

namespace Adminro\Controllers;

use App\Http\Controllers\Managers\ModelOwnerSettingsManager;
use Exception;
use Illuminate\Validation\ValidationException;

class Model
{
    protected $controllerSettings;
    protected $class;
    protected $model;

    public function __construct($controllerSettings)
    {
        $this->controllerSettings = $controllerSettings;
    }

    public function controllerSettings(): ControllerSettings
    {
        return $this->controllerSettings;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function setModel($model)
    {
        $this->model = $model;
    }

    public function class()
    {
        return $this->class;
    }

    public function model()
    {
        return $this->model;
    }

    public function find($id, $with_trashed = false)
    {
        $model = $this->class()::query();
        if ($with_trashed) {
            $model->withTrashed();
        }

        $this->setModel($model->find($id));
    }

    public function store()
    {
        try {
            $this->setModel($this->class()::create(filterModelValidated($this->class(), $this->controllerSettings()->request()->validated(), is_post_save: false)));
            postSaveModel($this->model(), filterModelValidated($this->class(), $this->controllerSettings()->request()->validated(), is_post_save: true), $this->controllerSettings()->formFields()->forms(), true, $this->controllerSettings()->info()->storeFolderName());
            $this->model()->save();
            $this->updateSettings(store: true);
            $this->controllerSettings()->route()->setSessionType('success');
        } catch (Exception $e) {
            throw ValidationException::withMessages([$e->getMessage()]);
        }
    }

    public function update()
    {
        try {
            $this->model()->update(filterModelValidated($this->class(), $this->controllerSettings()->request()->validated(), is_post_save: false));
            postSaveModel($this->model(), filterModelValidated($this->class(), $this->controllerSettings()->request()->validated(), is_post_save: true), $this->controllerSettings()->formFields()->forms(), false, $this->controllerSettings()->info()->storeFolderName());
            $this->model()->save();
            $this->updateSettings(remove: true, store: true);
            $this->controllerSettings()->route()->setSessionType('success');
        } catch (Exception $e) {
            throw ValidationException::withMessages([$e->getMessage()]);
        }
    }

    public function delete()
    {
        try {
            $this->model()->delete();
            $this->model()->update(['status' => 4]);
            $this->controllerSettings()->route()->setSessionType('success');
        } catch (Exception $e) {
            throw ValidationException::withMessages([$e->getMessage()]);
        }
    }

    public function restore()
    {
        try {
            $this->model()->restore();
            $this->model()->update(['status' => 1]);
            $this->controllerSettings()->route()->setSessionType('success');
        } catch (Exception $e) {
            throw ValidationException::withMessages([$e->getMessage()]);
        }
    }

    public function forceDelete()
    {
        try {
            $this->model()->forceDelete();
            postDeleteModel($this->model(), $this->controllerSettings()->formFields()->forms());
            $this->updateSettings(remove: true);
            $this->controllerSettings()->route()->setSessionType('success');
        } catch (Exception $e) {
            throw ValidationException::withMessages([$e->getMessage()]);
        }
    }

    public function removeFile($attribute)
    {
        try {
            removeFileFromStorage($this->model(), $attribute, $this->controllerSettings()->formFields()->form($attribute));
            $this->model()->update([$attribute => null, $attribute . '_path' => null]);
        } catch (Exception $e) {
            throw ValidationException::withMessages([$e->getMessage()]);
        }
    }

    public function updateSettings($remove = false, $store = false)
    {
        try {
            if ($remove) ModelOwnerSettingsManager::removeOwnerSettings($this->model());
            if ($store) ModelOwnerSettingsManager::storeOwnerSettings($this->model(), $this->controllerSettings()->request()->validated());
        } catch (Exception $e) {
            throw ValidationException::withMessages([$e->getMessage()]);
        }
    }
}
