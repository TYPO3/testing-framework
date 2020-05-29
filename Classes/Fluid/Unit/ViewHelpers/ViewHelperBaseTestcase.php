<?php
namespace TYPO3\TestingFramework\Fluid\Unit\ViewHelpers;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;

/**
 * Base test class for testing view helpers
 */
abstract class ViewHelperBaseTestcase extends UnitTestCase
{
    /**
     * @var ViewHelperVariableContainer|ObjectProphecy
     */
    protected $viewHelperVariableContainer;

    /**
     * @var StandardVariableProvider
     */
    protected $templateVariableContainer;

    /**
     * @var ControllerContext
     */
    protected $controllerContext;

    /**
     * @var TagBuilder
     */
    protected $tagBuilder;

    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var RenderingContext
     */
    protected $renderingContext;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->viewHelperVariableContainer = $this->prophesize(ViewHelperVariableContainer::class);
        $this->templateVariableContainer = $this->createMock(StandardVariableProvider::class);
        $this->request = $this->prophesize(Request::class);
        $this->controllerContext = $this->createMock(ControllerContext::class);
        $this->controllerContext->expects($this->any())->method('getRequest')->will($this->returnValue($this->request->reveal()));
        $this->arguments = [];
        $this->renderingContext = $this->getMockBuilder(RenderingContext::class)
            ->addMethods(['dummy'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->renderingContext->setVariableProvider($this->templateVariableContainer);
        $this->renderingContext->injectViewHelperVariableContainer($this->viewHelperVariableContainer->reveal());
        $this->renderingContext->setControllerContext($this->controllerContext);
    }

    /**
     * @param ViewHelperInterface $viewHelper
     * @return void
     */
    protected function injectDependenciesIntoViewHelper(ViewHelperInterface $viewHelper)
    {
        $viewHelper->setRenderingContext($this->renderingContext);
        $viewHelper->setArguments($this->arguments);
    }

    /**
     * Helper function to merge arguments with default arguments according to their registration
     * This usually happens in ViewHelperInvoker before the view helper methods are called
     *
     * @param ViewHelperInterface $viewHelper
     * @param array $arguments
     */
    protected function setArgumentsUnderTest(ViewHelperInterface $viewHelper, array $arguments = [])
    {
        $argumentDefinitions = $viewHelper->prepareArguments();
        foreach ($argumentDefinitions as $argumentName => $argumentDefinition) {
            if (!isset($arguments[$argumentName])) {
                $arguments[$argumentName] = $argumentDefinition->getDefaultValue();
            }
        }
        $viewHelper->setArguments($arguments);
    }
}
