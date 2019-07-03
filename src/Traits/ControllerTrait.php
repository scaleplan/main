<?php

namespace Scaleplan\Main\Traits;

use Scaleplan\Db\Db;
use function Scaleplan\DependencyInjection\get_required_container;
use Scaleplan\DTO\DTO;
use Scaleplan\DTO\Exceptions\PropertyNotFoundException;
use Scaleplan\Form\Form;
use Scaleplan\Form\Interfaces\FormInterface;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Http\Exceptions\NotFoundException;
use Scaleplan\HttpStatus\HttpStatusCodes;
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
                . get_required_env(ConfigConstants::VIEWS_PATH)
                . get_required_env(AbstractController::FORMS_PATH_ENV_NAME)
                . '/'
                . strtolower($this->getModelName())
                . '.yml'
            )
        );
        /** @var AbstractController $this */
        $form = get_required_container(FormInterface::class, [$formConfig]);

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
    public function actionCreate(FormInterface $form = null) : HTMLResultInterface
    {
        $form = $form ?? $this->getForm();
        return get_required_container(HTMLResultInterface::class, [$form->render()]);
    }

    /**
     * Форма редактирования модели
     *
     * @param DTO $idDto - идентификатор модели
     * @param DbResultInterface|null $model - Данные для заполнения формы
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
    public function actionEdit(
        DTO $idDto,
        DbResultInterface $model = null,
        FormInterface $form = null
    ) : HTMLResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }
        $model = $model ?? $repo->getFullInfo($idDto);
        if (!$model->getResult()) {
            throw new NotFoundException(
                'Объект с таким идентификатором не существует.',
                HttpStatusCodes::HTTP_NOT_FOUND
            );
        }

        $form = $form ?? $this->getForm('update');
        if (!isset($idDto->id)) {
            throw new PropertyNotFoundException('id');
        }

        $form->addIdField($idDto->getId());
        $form->setFormValues($model->getFirstResult());

        return get_required_container(HTMLResultInterface::class, [$form->render()]);
    }

    /**
     * Сохранить новую модель
     *
     * @param DTO $dto
     *
     * @return DbResultInterface
     *
     * @throws ControllerException
     */
    public function actionPut(DTO $dto) : DbResultInterface
    {
        try {
            /** @var AbstractController $this */
            $repo = $this->getRepository();

            /** @var DbResultInterface $result */
            $result = $repo->put($dto);
            if (!$result->getResult()) {
                throw new ControllerException('Не удалось создать объект.');
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === Db::DUPLICATE_ERROR_CODE) {
                throw new ControllerException('Такая сущность уже есть в системе.', HttpStatusCodes::HTTP_CONFLICT);
            }

            throw $e;
        }

        return $result;
    }

    /**
     * Изменить модель
     *
     * @param DTO $id
     * @param DTO $dto
     *
     * @return DbResultInterface
     *
     * @throws ControllerException
     */
    public function actionUpdate(DTO $id, DTO $dto) : DbResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->update($id->toSnakeArray() + $dto->toSnakeArray());
        if (!$result->getResult()) {
            throw new ControllerException(
                'Не удалось изменить объект. Возможно, объект не существует.',
                HttpStatusCodes::HTTP_NOT_FOUND
            );
        }

        return $result;
    }

    /**
     * Удаление модели
     *
     * @param DTO $id - идентификатор модели
     *
     * @return DbResultInterface
     *
     * @throws ControllerException
     */
    public function actionDelete(DTO $id) : DbResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        $result = $repo->delete($id);
        if (!$result->getResult()) {
            throw new ControllerException('Не удалось удалить объект.', HttpStatusCodes::HTTP_NOT_FOUND);
        }

        return $result;
    }

    /**
     * Сжатая информация о модели
     *
     * @param DTO $id - идентификатор модели
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
    public function actionInfo(DTO $id) : ResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->getInfo($id);
        if (!$result->getResult()) {
            throw new ControllerException(
                'Объект с таким идентификатором не существует.',
                HttpStatusCodes::HTTP_NOT_FOUND
            );
        }
        /** @var AbstractController $this */
        return $this->formatResponse($result);
    }

    /**
     * Полная информация о модели
     *
     * @param DTO $id - идентификатор модели
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
    public function actionFullInfo(DTO $id) : ResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->getFullInfo($id);
        if (!$result->getResult()) {
            throw new NotFoundException(
                'Объект с таким идентификатором не существует.',
                HttpStatusCodes::HTTP_NOT_FOUND
            );
        }
        /** @var AbstractController $this */
        return $this->formatResponse($result);
    }

    /**
     * Список объектов
     *
     * @param DTO|array $dto
     *
     * @return ResultInterface
     *
     * @throws ControllerException
     */
    public function actionList($dto) : ResultInterface
    {
        /** @var AbstractController $this */
        $repo = $this->getRepository();
        if (!$repo) {
            throw new ControllerException('Репозиторий не найден.');
        }

        $result = $repo->getList($dto);

        return $this->formatResponse($result);
    }
}
