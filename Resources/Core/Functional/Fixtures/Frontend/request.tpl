<?php
// @deprecated Can be removed when retrieveFrontendRequestResult() is dropped
require '{vendorPath}typo3/testing-framework/Classes/Core/Functional/Framework/Frontend/RequestBootstrap.php';
(new \TYPO3\TestingFramework\Core\Functional\Framework\Frontend\RequestBootstrap('{documentRoot}', {arguments}))
    ->executeAndOutput();
?>
