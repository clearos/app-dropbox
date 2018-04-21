
Name: app-dropbox
Epoch: 1
Version: 2.5.0
Release: 1%{dist}
Summary: Dropbox
License: GPLv3
Group: Applications/Apps
Packager: ClearFoundation
Vendor: ClearFoundation
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base
Requires: app-base
Requires: app-user-dropbox

%description
Dropbox is a file hosting service operated by Dropbox, Inc. that offers cloud storage, file synchronization and client software.

%package core
Summary: Dropbox - API
License: LGPLv3
Group: Applications/API
Requires: app-base-core
Requires: dropbox >= 19.4.12
Requires: app-user-dropbox-core >= 1:1.6.0
Requires: app-users-core
Requires: app-base-core >= 1:2.4.13
Requires: app-user-dropbox-plugin-core

%description core
Dropbox is a file hosting service operated by Dropbox, Inc. that offers cloud storage, file synchronization and client software.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/dropbox
cp -r * %{buildroot}/usr/clearos/apps/dropbox/

install -D -m 0644 packaging/dropbox.conf %{buildroot}/etc/clearos/dropbox.conf
install -D -m 0644 packaging/dropbox.php %{buildroot}/var/clearos/base/daemon/dropbox.php

%post
logger -p local6.notice -t installer 'app-dropbox - installing'

%post core
logger -p local6.notice -t installer 'app-dropbox-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/dropbox/deploy/install ] && /usr/clearos/apps/dropbox/deploy/install
fi

[ -x /usr/clearos/apps/dropbox/deploy/upgrade ] && /usr/clearos/apps/dropbox/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-dropbox - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-dropbox-core - uninstalling'
    [ -x /usr/clearos/apps/dropbox/deploy/uninstall ] && /usr/clearos/apps/dropbox/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/dropbox/controllers
/usr/clearos/apps/dropbox/htdocs
/usr/clearos/apps/dropbox/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/dropbox/packaging
%exclude /usr/clearos/apps/dropbox/unify.json
%dir /usr/clearos/apps/dropbox
/usr/clearos/apps/dropbox/deploy
/usr/clearos/apps/dropbox/language
/usr/clearos/apps/dropbox/libraries
%config(noreplace) /etc/clearos/dropbox.conf
/var/clearos/base/daemon/dropbox.php
