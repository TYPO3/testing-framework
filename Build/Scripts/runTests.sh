#!/usr/bin/env bash

#
# Test runner based on docker and docker-compose.
#

# Function to write a .env file in Build/testing-docker/local
# This is read by docker-compose and vars defined here are
# used in Build/testing-docker/docker-compose.yml
setUpDockerComposeDotEnv() {
    # Delete possibly existing local .env file if exists
    [ -e .env ] && rm .env
    # Set up a new .env file for docker-compose
    echo "COMPOSE_PROJECT_NAME=local" >> .env
    # To prevent access rights of files created by the testing, the docker image later
    # runs with the same user that is currently executing the script. docker-compose can't
    # use $UID directly itself since it is a shell variable and not an env variable, so
    # we have to set it explicitly here.
    echo "HOST_UID=`id -u`" >> .env
    # Your local home directory for composer and npm caching
    echo "HOST_HOME=${HOME}" >> .env
    # Your local user
    echo "ROOT_DIR"=${ROOT_DIR} >> .env
    echo "HOST_USER=${USER}" >> .env
    echo "PHP_XDEBUG_ON=${PHP_XDEBUG_ON}" >> .env
    echo "DOCKER_PHP_IMAGE=${DOCKER_PHP_IMAGE}" >> .env
    echo "SCRIPT_VERBOSE=${SCRIPT_VERBOSE}" >> .env
    echo "CGLCHECK_DRY_RUN=${CGLCHECK_DRY_RUN}" >> .env
}

# Load help text into $HELP
read -r -d '' HELP <<EOF
testing-framework test runner. Execute unit test suite and some other details.
Also used by github actions for test execution.

Usage: $0 [options]

No arguments: Run all unit tests with PHP 8.1

Options:
    -s <...>
        Specifies which test suite to run
            - cgl: test and fix all php files
            - clean: clean up build and testing related files
            - composerUpdate: "composer update"
            - lint: PHP linting
            - unit (default): PHP unit tests

    -p <8.1>
        Specifies the PHP minor version to be used
            - 8.1 (default): use PHP 8.1

    -x
        Only with -s cgl|unit
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -n
        Only with -s cgl
        Activate dry-run in CGL check that does not actively change files and only prints broken ones.

    -v
        Enable verbose script output. Shows variables and docker commands.

    -h
        Show this help.

Examples:
    # Run unit tests using default PHP version
    ./Build/Scripts/runTests.sh

    # Run unit tests using PHP 8.1
    ./Build/Scripts/runTests.sh -p 8.1
EOF

# Test if docker-compose exists, else exit out with error
if ! type "docker-compose" > /dev/null; then
  echo "This script relies on docker and docker-compose. Please install" >&2
  exit 1
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called.
THIS_SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
cd "$THIS_SCRIPT_DIR" || exit 1

# Go to directory that contains the local docker-compose.yml file
cd ../testing-docker || exit 1

# Option defaults
ROOT_DIR=`readlink -f ${PWD}/../../`
TEST_SUITE="unit"
PHP_VERSION="8.1"
PHP_XDEBUG_ON=0
SCRIPT_VERBOSE=0
CGLCHECK_DRY_RUN=""

# Option parsing
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=();
# Simple option parsing based on getopts (! not getopt)
while getopts ":s:p:hxnv" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            ;;
        h)
            echo "${HELP}"
            exit 0
            ;;
        n)
            CGLCHECK_DRY_RUN="-n"
            ;;
        v)
            SCRIPT_VERBOSE=1
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        \?)
            INVALID_OPTIONS+=(${OPTARG})
            ;;
        :)
            INVALID_OPTIONS+=(${OPTARG})
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "${HELP}" >&2
    exit 1
fi

# Move "7.2" to "php72", the latter is the docker container name
DOCKER_PHP_IMAGE=`echo "php${PHP_VERSION}" | sed -e 's/\.//'`

if [ ${SCRIPT_VERBOSE} -eq 1 ]; then
    set -x
fi

# Suite execution
case ${TEST_SUITE} in
    cgl)
        # Active dry-run for cgl needs not "-n" but specific options
        if [[ ! -z ${CGLCHECK_DRY_RUN} ]]; then
            CGLCHECK_DRY_RUN="--dry-run --diff"
        fi
        setUpDockerComposeDotEnv
        docker-compose run cgl
        SUITE_EXIT_CODE=$?
        docker-compose down
        ;;
    clean)
        rm -rf ../../composer.lock ../../.Build/ ../../public
        ;;
    composerUpdate)
        setUpDockerComposeDotEnv
        docker-compose run composer_update
        SUITE_EXIT_CODE=$?
        docker-compose down
        ;;
    lint)
        setUpDockerComposeDotEnv
        docker-compose run lint
        SUITE_EXIT_CODE=$?
        docker-compose down
        ;;
    unit)
        setUpDockerComposeDotEnv
        docker-compose run unit
        SUITE_EXIT_CODE=$?
        docker-compose down
        ;;
    *)
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
esac

exit $SUITE_EXIT_CODE
