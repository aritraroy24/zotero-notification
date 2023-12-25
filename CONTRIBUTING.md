# Contributing to Zotero Notification

First off, thank you for considering contributing to Zotero Notification. It's people like you that make Zotero Notification such a great tool.

## Where do I go from here?

If you've noticed a bug or have a feature request, make one! It's generally best if you get confirmation of your bug or approval for your feature request this way before starting to code.

## Fork & create a branch

If this is something you think you can fix, then fork and create a branch with a descriptive name.

A good branch name would be (where issue #325 is the ticket you're working on):

```bash
git checkout -b 325-add-pdf-export-feature
```
## Get the Test Suite Running

Make sure you're testing the changes you're making. Refer to the [README.md](README.md) for instructions on how to run the project. For testing locally, you can use [XAMPP](https://www.apachefriends.org/index.html) or [WAMP](http://www.wampserver.com/en/) server.

## Make the Change

Work on the code to fix the issue or add the feature.

## Make a Pull Request

At this point, you should switch back to your master branch and make sure it's up to date with Zotero Notification's master branch:

```bash
git remote add upstream git@github.com:aritraroy24/zotero-notification.git
git checkout master
git pull upstream master
```
Then update your feature branch from your local copy of master, and push it!

```bash
git checkout 325-add-pdf-export-feature
git rebase master
git push --set-upstream origin 325-add-pdf-export-feature
```

Finally, go to GitHub and make a [Pull Request](https://help.github.com/articles/using-pull-requests/) :D