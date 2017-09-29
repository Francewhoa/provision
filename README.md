# Provision 4.x

This branch of Provision is the first steps toward converting a drush package to a Symfony Console CLI tool.

It is IN ACTIVE DEVELOPMENT: Most things will not work yet.

The plan is to create a Provision.phar CLI tool with all of the same commands that drush provision contains.

If we attain Command-Parity with drush provision, we can switch out the command Hosting.module uses when running tasks to use provision.phar instead of drush_backend_invoke().

This way we do not have to redesign the whole system: Just replace provision.

See this issue for more information on this effort: https://www.drupal.org/node/2912579

Thoughts welcome.

 -JonPugh

# The Aegir system

The Aegir hosting system allows developers and site administrators to
automate many of the common tasks associated with deploying and
managing large websites. Aegir makes it easy to install, upgrade,
deploy, and backup an entire network of Drupal sites.

The most up to date information regarding the project and its goals
can be found on the Aegir website and the documentation pages:

   http://aegirproject.org
   http://docs.aegirproject.org/

This is the backend of the Aegir hosting system. The front end
(hostmaster) and the backend (provision) are designed to be run
separately, and each front end is able to drive multiple back
ends. Aegir can install itself with the software you have already
downloaded alongside this readme file.

To install Aegir, you should follow the instructions at:

   http://docs.aegirproject.org/en/3.x/install/

To upgrade Aegir, follow the instructions at:

   http://docs.aegirproject.org/en/3.x/install/upgrade

If you have further questions or are having trouble with Aegir,
check out how to reach our community at:

   http://docs.aegirproject.org/en/3.x/community/

Other documentation for developers is also available at:

   http://docs.aegirproject.org/en/3.x/extend/

Build status Travis: [![Build Status](https://travis-ci.org/aegir-project/provision.svg?branch=7.x-3.x)](https://travis-ci.org/aegir-project/provision) (in-depth and manual install)

Build status GitLab CI: [![build status](https://gitlab.com/aegir/provision/badges/7.x-3.x/build.svg)](https://gitlab.com/aegir/provision/commits/7.x-3.x) (debian packages)
