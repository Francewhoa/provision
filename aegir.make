core = 6.x
api = 2

; BOA-2.0.3-dev

projects[pressflow][type] = "core"
projects[pressflow][download][type] = "get"
projects[pressflow][download][url] = "http://files.aegir.cc/dev/pressflow-6.26.1.tar.gz"
projects[pressflow][download][branch] = "master"

projects[hostmaster][type] = "profile"
projects[hostmaster][download][type] = "git"
projects[hostmaster][download][url] = "MAKEFILE_REPO_GIT_URL/hostmaster.git"
projects[hostmaster][download][branch] = "master"
