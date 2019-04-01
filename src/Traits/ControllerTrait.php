<?php

namespace Scaleplan\Main\Traits;

use Scaleplan\Db\Db;
use function Scaleplan\DependencyInjection\get_container;
use Scaleplan\DTO\DTO;
use Scaleplan\Form\Form;
use Scaleplan\Form\Interfaces\FormInterface;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Http\Exceptions\NotFoundException;
use Scaleplan\Main\AbstractController;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\ControllerException;
use Scaleplan\Result\Interfaces\DbResultInterface;
use Scaleplan\Result\Interfaces\HTMLResultInterface;
use Scaleplan\Result\Interfaces\ResultInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait ControllerTrait
 *
 * @package Scaleplan\Main\Traits
 */
trait ControllerTrait
{
    /**
     * Шаблон формы добавления/изменения объекта
     *
     * @param string $type - тип формы ('put' - добавление или 'update' - изменение)
     *
     * @return Form
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    private function getForm(string $type = 'put') : Form
    {
        $formConfig = Yaml::parse(
            file_get_contents(
                get_required_env(ConfigConstants::BUNDLE_PATH)
                . get_required_env(AbstractController::FORMS_PATH_ENV_NAME)
                . '/'
                . strtolower($this->getServiceName())
                . '.yml'
            )
        );
        /** @var AbstractController $this */
        $form = get_container(FormInterface::class, [$formConfig]);

        if (!empty($form->getFormConf()['form']['action'][$type])) {
            $form->setFormAction($form->getFormConf()['form']['action'][$type]);
        }

        if (!empty($form->getFormConf()['title']['text'][$type])) {
            $form->setTitleText($form->getFormConf()['title']['text'][$type]);
        }

        return $form;
    }

    /**
     * Форма создания
     *
     * @accessMethod
     *
     * @param FormInterface|null $form
     *
     * @return HTMLResultInterface
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Exception
     */
    protected function actionCreate(FormInterface $form = null) : HTMLResultInterface
    {
        $form = $form ?? $this->getForm();
        return get_container(HTMLResultInterface::class, [$form->render()]);
    }

    /**
     * Форма редактирования модели
     *
     * @accessMethod
     *
     * @accessFilter id
     *
     * @param int $id - идентификатор модели
     * @param FormInterface|null $form
     *
     * @return HTMLResultInterface
     *
     * @throws ControllerException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Form\Exceptions\FieldException
     * @throws \Scaleplan\Form\Exceptions\FormException
     * @throws \Scaleplan\Form\Exceptions\RadioVariantException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Exception
     */
    protected function actionEdit(int $id, FormInterface $form = null) : HTMLResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }
        $result = $repo->getFullInfo(['id' => $id]);
        if (!$result->getResult()) {
            throw new NotFoundException('Объект с таким идентификатором не существует.', 404);
        }

        $form = $form ?? $this->getForm('update');
        $form->addIdField($id);
        $form->setFormValues($result->getFirstResult());

        return get_container(HTMLResultInterface::class, [$form->render()]);
    }

    /**
     * Сохранить новую модель
     *
     * @accessMethod
     *
     * @param DTO $dto
     *
     * @return DbResultInterface
     *
     * @throws ControllerException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    protected function actionPut(DTO $dto) : DbResultInterface
    {
        try {
            /** @var AbstractController $this */
            $repo = $this->getRepository();
            if (!$repo) {
                throw new ControllerException('Репозиторий не найден.');
            }

            $result = $repo->put($dto);
            if (!$result->getResult()) {
                throw new ControllerException('Не удалось создать объект.');
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === Db::DUPLICATE_ERROR_CODE) {
                throw new ControllerException('Такая сущность уже есть в системе.');
            }

            throw $e;
        }
    }

    /**
     * Изменить модель
     *
     * @accessMethod
     *
     * @accessFilter id
     *
     * @param int $id
     * @param DTO $dto
     *
     * @return DbResultInterface
     *
     * @throws ControllerException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    protected function actionUpdate(int $id, DTO $dto) : DbResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->update($id, $dto);
        if (!$result->getResult()) {
            throw new ControllerException('Не удалось изменить объект.');
        }

        return $result;
    }

    /**
     * Удаление модели
     *
     * @accessMethod
     *
     * @accessFilter id
     *
     * @param int $id - идентификатор модели
     *
     * @return DbResultInterface
     *
     * @throws ControllerException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    protected function actionDelete(int $id) : DbResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->delete(['id' => $id]);
        if (!$result->getResult()) {
            throw new ControllerException('Не удалось удалить объект.');
        }

        return $result;
    }

    /**
     * Сжатая информация о модели
     *
     * @accessMethod
     *
     * @accessFilter id
     *
     * @param int $id - идентификатор модели
     *
     * @return ResultInterface
     *
     * @throws ControllerException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    protected function actionGetInfo(int $id) : ResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->getInfo(['id' => $id]);
        if (!$result->getResult()) {
            throw new ControllerException('Не удалось удалить объект');
        }
        /** @var AbstractController $this */
        return $this->formatResponse($result);
    }

    /**
     * Полная информация о модели
     *
     * @accessMethod
     *
     * @accessFilter id
     *
     * @param int $id - идентификатор модели
     *
     * @return ResultInterface
     *
     * @throws ControllerException
     * @throws NotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    protected function actionGetFullInfo(int $id) : ResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->getFullInfo(['id' => $id]);
        if (!$result->getResult()) {
            throw new NotFoundException('Объект с таким идентификатором не существует.', 404);
        }
        /** @var AbstractController $this */
        return $this->formatResponse($result);
    }

    /**
     * Список моделей
     *
     * @accessMethod
     *
     * @param DTO $dto
     *
     * @return ResultInterface
     * @throws ControllerException
     * @throws NotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    protected function actionGetList(DTO $dto) : ResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->getFullInfo($dto);
        if (!$result->getResult()) {
            throw new NotFoundException('Объект с таким идентификатором не существует.', 404);
        }
        /** @var AbstractController $this */
        return $this->formatResponse($result);
    }
}
