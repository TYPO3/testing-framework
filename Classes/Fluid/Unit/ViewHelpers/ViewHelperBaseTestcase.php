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
 *
 * Deprecation note:
 * View helper tests that rely on this class also rely on heavy mocking. This
 * makes the view helper tests created with this class rather brittle and hard
 * to understand since they rely a lot on internal fluid knowledge.
 *
 * It is much easier and robust to create functional tests for view helpers.
 * The v11 core has a lot of examples for this, and many simple cases boil down
 * to creating an instance of a view and feeding a template to it.
 *
 * A simple example for a functional test exending FunctionalTestCase:
 *
 * $view = new StandaloneView();
 * $view->setTemplateSource('<f:format.crop maxCharacters="10">Crop this content</f:format.crop>');
 * self::assertSame('Crop this&hellip;', $view->render());
 *
 * Have a look at further core examples to get rid of this class,
 * especially functional tests in ext:fluid.
 *
 * @deprecated Will be dropped with 7.x major version.
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

    protected function setUp(): void
    {
        $this->viewHelperVariableContainer = $this->prophesize(ViewHelperVariableContainer::class);
        $this->templateVariableContainer = $this->createMock(StandardVariableProvider::class);
        $this->request = $this->prophesize(Request::class);
        $this->controllerContext = $this->createMock(ControllerContext::class);
        $this->controllerContext->expects(self::any())->method('getRequest')->willReturn($this->request->reveal());
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
