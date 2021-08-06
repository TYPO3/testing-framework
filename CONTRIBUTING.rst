===========================================
Contributing to ``typo3/testing-framework``
===========================================

TYPO3 testing framework, like the TYPO3 CMS in general, is a project of
volunteers. We encourage you to join the project by submitting changes.

.. contents:: Table of Contents


Coder
=====

Getting started
---------------

Before you begin:

1. Fork the testing framework.

2. Set up a locally running TYPO3 CMS instance for which you want to deploy a
   change in the testing framework, for example by following the
   `installation guide <https://docs.typo3.org/m/typo3/guide-contributionworkflow/master/en-us/Setup/SetupTypo3.html>`_
   in the official documentation.

3. Replace the testing framework installed by composer with the forked testing
   framework from GitHub:

   .. code-block:: bash

      cd vendor/typo3
      rm -rf testing-framework
      git clone git@github.com:[YOUR_USERNAME]/testing-framework.git

4. Confirm that the unit tests of the current TYPO3 CMS code base pass
   successfully, for example by:

   .. code-block:: bash

      ./Build/Scripts/runTests.sh -s unit -e "--stop-on-failure"

Now you have a working TYPO3 CMS with the forked testing framework at
``vendor/typo3/testing-framework`` ready for changes.

Creating an issue
-----------------

Before you make your changes, check if an issue already exists for the change
you want to make.

If you discover something new, open an issue. We use the issue to start a
conversation about the problem you want to fix.

If there is an existing issue related to your problem, join the conversation to
provide additional perspective or encourage us to bring attention to that bug or
feature request.

Making a change
---------------

To provide a change to the testing framework, you have to

1. make your changes to the files you want to update,
2. test your changes in the specific use case that caused the problem and
   confirm that it fixes the problem,
3. test your changes for compatibility with the TYPO3 CMS by running the TYPO3
   Core tests:

   *  TYPO3 Core unit tests (fast ~ few minutes):

      .. code-block:: bash

         ./Build/Scripts/runTests.sh -s unit

   *  TYPO3 Core functional tests (slow ~ 30-60 minutes):

      .. code-block:: bash

         ./Build/Scripts/runTests.sh -s functional

   *  TYPO3 Core acceptance tests (slow ~ 30-60 minutes):

      .. code-block:: bash

         ./Build/Scripts/runTests.sh -s acceptance

   and confirming that the test runs have the same result as without the
   changes,

4. push the change to your fork by

   *  creating a specific branch
   *  creating a commit with a detailed commit message
      (get inspired by the existing commits)
   *  pushing the branch
   *  ensuring the remote branch is not protected to facilitate collaboration

5. create a pull request (PR) of your change by

   *  creating the PR
   *  adding steps to reproduce the problem and test the solution in the PR
      description
   *  adding results of the successful TYPO3 Core test runs in the PR
      description
   *  adding a link to the related issue in the PR description

6. get the PR approved by

   *  request reviews of your PR
   *  incorporate feedback
   *  get approved by at least two reviewers
   *  run the TYPO3 Core tests (see above) one last time and comment it in the
      PR.

Now you are done and we will merge your change soon.


Reviewer
========

Getting started
---------------

1. Set up a locally running TYPO3 CMS instance for which you want to test the
   change in the testing framework, for example by following the
   `installation guide <https://docs.typo3.org/m/typo3/guide-contributionworkflow/master/en-us/Setup/SetupTypo3.html>`_
   in the official documentation.

2. Replace the testing framework installed by composer with the testing
   framework containing the change from GitHub:

   .. code-block:: bash

      cd vendor/typo3
      rm -rf testing-framework
      git clone git@github.com:[USERNAME_OF_CODER]/testing-framework.git
      git checkout [BRANCH_OF_CHANGE]

Now you have a working TYPO3 CMS with the changed testing framework at
``vendor/typo3/testing-framework`` ready for testing.

Creating a review
-----------------

1. Provide a code review and require changes by the coder in a feedback loop in
   the PR.

2. Test the changes for compatibility with TYPO3 CMS by running the
   TYPO3 Core unit tests and confirm that all tests pass successfully:

   *  TYPO3 Core unit tests (fast ~ few minutes):

      .. code-block:: bash

         ./Build/Scripts/runTests.sh -s unit -e "--stop-on-failure"

   Add the results of your tests to the review description in the PR.

3. Approve the PR.
