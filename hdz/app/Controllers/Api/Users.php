<?php
/**
 * @package EvolutionScript
 * @author: EvolutionScript S.A.C.
 * @Copyright (c) 2010 - 2020, EvolutionScript.com
 * @link http://www.evolutionscript.com
 */

namespace App\Controllers\Api;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;
use Psr\Log\LoggerInterface;

class Users extends ResourceController
{
    protected $format = 'json';
    protected $modelName = 'App\Models\Users';
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger); // TODO: Change the autogenerated stub
        helper(['form','html','helpdesk','number','filesystem','text']);
    }

    public function create()
    {
        $api = Services::api();
        if(!$api->validatePermission('users/create')){
            return $api->showError();
        }
        $validation = Services::validation();
        $validation->setRules([
            'fullname' => 'required',
            'email' => 'required|valid_email|is_unique[users.email]',
        ],[
            'fullname' => [
                'required' => lang('Api.error.fullNameMissing')
            ],
            'email' => [
                'required' => lang('Api.error.emailMissing'),
                'valid_email' => lang('Api.error.emailNotValid'),
                'is_unique' => lang('Api.error.emailTaken')
            ]
        ]);
        if($validation->withRequest($this->request)->run() == false){
            return $api->output(implode(' ',array_values($validation->getErrors())), true);
        }else{
            $client = Services::client();
            $client_id = $client->createAccount($this->request->getPost('fullname'), $this->request->getPost('email'),'', ($this->request->getPost('notify') == 1 ? true : false), $this->request->getPost('user_id'));
            return $api->output([
                'user_id' => $client_id,
                'message' => lang('Api.userCreated')
            ]);
        }
    }

    public function index()
    {
        $api = Services::api();
        if(!$api->validatePermission('users/read')){
            return $api->showError();
        }

        if(defined('HDZDEMO')){
            $this->model->select('id, fullname, \'[Hidden in demo]\' as email');
        }else{
            $this->model->select('id, fullname, email');
        }

        if($this->request->getGet('email') != ''){
            $this->model->where('email', $this->request->getGet('email'));
            $q = $this->model->get(1);
            if($q->resultID->num_rows == 0){
                return $api->output(lang('Api.error.userNotFound'), true);
            }else{
                return $api->output(['user_data' => $q->getRow()]);
            }
        }else{
            $page = (is_numeric($this->request->getGet('page')) ? $this->request->getGet('page') : 1);
            if($page <= 0 || $page != round($page)){
                return $api->output(lang('Api.error.pageNotFound'), true);
            }
            $result = $this->model->orderBy('id','desc')
                ->paginate(25, 'default', $page);
            $pager = $this->model->pager;
            return $api->output([
                'total_users' => $pager->getDetails()['total'],
                'total_pages' => $pager->getLastPage(),
                'users' => $result
            ]);
        }
    }

    public function show($id=null)
    {
        $api = Services::api();
        if(!$api->validatePermission('users/read')){
            return $api->showError();
        }
        if(defined('HDZDEMO')){
            $this->model->select('id, fullname, \'[Hidden in demo]\' as email');
        }else{
            $this->model->select('id, fullname, email');
        }
        $result = $this->model->find($id);
        if(!$result){
            return $api->output(lang('Api.error.userNotFound'), true);
        }else{
            return $api->output(['user_data' => $result]);
        }
    }

    public function update($id=null)
    {
        $api = Services::api();
        if(!$api->validatePermission('users/update')){
            return $api->showError();
        }
        $result = $this->model->find($id);
        if(!$result){
            return $api->output(lang('Api.error.userNotFound'), true);
        }

        if($result->email == $this->request->getPost('new_email')){
            return $api->output(lang('Api.emailChanged'));
        }

        $validation = Services::validation();
        $validation->setRule('new_email','E-mail address', 'required|valid_email|is_unique[users.email]',[
            'required' => lang('Api.error.emailMissing'),
            'valid_email' => lang('Api.error.emailNotValid'),
            'is_unique' => lang('Api.error.emailTaken')
        ]);
        if($validation->withRequest($this->request)->run() == false){
            return $api->output(implode(' ', array_values($validation->getErrors())), true);
        }
        $client = Services::client();
        $client->update([
            'email' => $this->request->getPost('new_email')
        ], $result->id);
        return $api->output(lang('Api.emailChanged'));
    }

    public function delete($id=null)
    {
        $api = Services::api();
        if(!$api->validatePermission('users/delete')){
            return $api->showError();
        }
        $result = $this->model->find($id);
        if(!$result){
            return $api->output(lang('Api.error.userNotFound'), true);
        }
        $client = Services::client();
        $client->deleteAccount($id);
        return $api->output(lang('Api.userRemoved'));
    }
}
