# XLS Exporter for TYPO3

## Export complex data to Excel sheets

### What does it do?

This extension helps to export complex data from TYPO3 into spreadsheets.

### How does it work?

This extension provides a new backend module for exporting complex data
into spreadsheets.

In the default setup the table tt_address can be exported out of the box.

### Configuration

The module can be configured by TSconfig. The following snippet has to be
added to your PageTS configuration.

This is the Default Setting:
```
module.tx_xlsexport {
    settings {
        exports {
            # name in settings, could be everything
            tt_address {
                # label shown in bakcned module
                label = Addresses
                # counter for selected records to export. String with SQL query
                check = SELECT count(*) FROM tt_address where pid=%d and hidden=0 and deleted=0
                # export query
                export (
                    select uid,first_name,middle_name,last_name,address,building,room,city,zip,region,country,phone,fax,email,www,title,company  from tt_address where pid=%d and deleted=0 and hidden=0
                )
                # list query, currently unused
                list (
                    select uid,first_name,last_name from tt_address where pid=%d and deleted=0 and hidden=0
                )
                # SQL table name
                table = tt_address
                # field names from SQL query for export
                exportfields {
                    10 = uid
                    20 = first_name
                    30 = middle_name
                    40 = last_name
                    50 = address
                    60 = building
                    70 = room
                    80 = city
                    90 = zip
                    100 = region
                    110 = country
                    120 = phone
                    130 = fax
                    140 = email
                    150 = www
                    160 = title
                    170 = company
                }
                # labels for export field columns, numbers must match with fields in exportfields
                exportfieldnames {
                    10 = lfd. Nummer
                    20 = Vorname
                    30 = Mittelname
                    40 = Nachname
                    50 = Adresse
                    60 = Gebäude
                    70 = Raum
                    80 = Stadt
                    90 = PLZ
                    100 = Region
                    110 = Land
                    120 = Telefon
                    130 = Fax
                    140 = E-Mail
                    150 = Web
                    160 = Titel
                    170 = Firma
                }
            }
        }
    }
}
```
which you can either override, or extend in this fashion:

```
module.tx_xlsexport {
    settings {
        export {
            myExport {
                # [...]
            }
        }
    }
}
```

Relations can be done by joins inside the select statements

TODO:
- add Events/Signalslots for datamanipulation
- add support for multiple sheets
- documentation
- localization

FUTURE: (hit me up if you are willing to help funding)
- support TCA for complex data structures
- update backend module to fit TYPO3 styles

## Create a release (maintainers only)

Prerequisites:

* git binary
* ssh key allowed to push new branches to the repository
* GitHub command line tool `gh` installed and configured with a user allowed to create and merge pull requests
* [`tailor`](https://github.com/TYPO3/tailor) available as `tailor`

> Set `RELEASE_BRANCH` to the branch the release happens on, for example `main`.
> Set `RELEASE_VERSION` to the version to release, for example `5.1.0`.
> Set `NEXT_DEV_VERSION` to the version development continues with, for example `5.1.1`.

```bash
echo '>> Create release' ; \
  RELEASE_BRANCH='main' ; \
  RELEASE_VERSION='5.1.0' ; \
  NEXT_DEV_VERSION='5.1.1' ; \
  git checkout main && \
  git fetch --all && \
  git pull --rebase && \
  git checkout ${RELEASE_BRANCH} && \
  git pull --rebase && \
  git checkout -b release-${RELEASE_VERSION} && \
  sed -i "s/^COMPOSER_ROOT_VERSION.*/COMPOSER_ROOT_VERSION=\"${RELEASE_VERSION}\"/" Build/Scripts/runTests.sh && \
  tailor set-version ${RELEASE_VERSION} && \
  echo "${RELEASE_VERSION}" > VERSION && \
  git add -u && \
  git commit -m "[RELEASE] ${RELEASE_VERSION}" && \
  git push --set-upstream origin release-${RELEASE_VERSION} && \
  gh pr create --fill --base ${RELEASE_BRANCH} --title "[RELEASE] ${RELEASE_VERSION}" && \
  gh pr view --web && \
  sleep 30 && \
  gh pr checks --watch --interval 2 && \
  sleep 5 && \
  gh pr merge -rd --admin && \
  git remote prune origin && \
  git tag ${RELEASE_VERSION} && \
  git push --tags && \
  git checkout -b set-dev-version-${NEXT_DEV_VERSION} && \
  tailor set-version ${NEXT_DEV_VERSION} && \
  echo "${NEXT_DEV_VERSION}-dev" > VERSION && \
  sed -i "s/^COMPOSER_ROOT_VERSION.*/COMPOSER_ROOT_VERSION=\"${NEXT_DEV_VERSION}-dev\"/" Build/Scripts/runTests.sh && \
  sed -i "s/^  RELEASE_VERSION=.*/  RELEASE_VERSION='${RELEASE_VERSION}' ; \\\/" README.md && \
  sed -i "s/^  NEXT_DEV_VERSION=.*/  NEXT_DEV_VERSION='${NEXT_DEV_VERSION}' ; \\\/" README.md && \
  git add -u && \
  git commit -m "[TASK] Set \"${NEXT_DEV_VERSION}-dev\"" && \
  git push --set-upstream origin set-dev-version-${NEXT_DEV_VERSION} && \
  gh pr create --fill --base ${RELEASE_BRANCH} --title "[TASK] Set \"${NEXT_DEV_VERSION}-dev\"" && \
  gh pr view --web && \
  sleep 30 && \
  gh pr checks --watch --interval 2 && \
  sleep 5 && \
  gh pr merge -rd --admin && \
  git remote prune origin
```

Pushing the tag triggers the `publish` workflow. It verifies that the tag matches `VERSION` and `ext_emconf.php`,
bundles PhpSpreadsheet for classic installations, creates the GitHub release and uploads the release to the TYPO3
Extension Repository.
