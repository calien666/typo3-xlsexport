:navigation-title: Installation

..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  contents:: Table of contents
    :local:

Requirements
============

*   TYPO3 13.4 LTS or 14.3 LTS
*   PHP 8.2, 8.3, 8.4 or 8.5

Install with Composer
=====================

..  code-block:: bash

    composer require calien/xlsexport

Activate the site set
=====================

The extension ships an example export as the site set :guilabel:`XLS Exporter`
(``calien/xlsexport``). Assign it to a site to make the example available on that site's pages:

..  code-block:: yaml
    :caption: config/sites/<my-site>/config.yaml

    dependencies:
      - calien/xlsexport

The set delivers the example page TSconfig under ``mod.web_xlsexport``. Replace it with your own
exports as described in :ref:`configuration`. The backend module itself is always available under
:guilabel:`Web > XLS Exporter` and needs no set to work.
