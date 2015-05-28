
Name: app-dropbox
Epoch: 1
Version: 1.7.0
Release: 1%{dist}
Summary: Dropbox
License: GPLv3
Group: ClearOS/Apps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base

%description
Dropbox is a file hosting service operated by Dropbox, Inc. that offers cloud storage, file synchronization and client software.

%package core
Summary: Dropbox - Core
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: dropbox >= 2.10.28
Requires: app-user-dropbox >= 1:1.6.0
Requires: app-users-core
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

install -D -m 0644 packaging/app-dropbox.cron %{buildroot}/etc/cron.d/app-dropbox
install -D -m 0644 packaging/dropbox.conf %{buildroot}/etc/clearos/dropbox.conf
install -D -m 0744 packaging/dropboxconf %{buildroot}/usr/sbin/dropboxconf

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
%dir /usr/clearos/apps/dropbox
/usr/clearos/apps/dropbox/deploy
/usr/clearos/apps/dropbox/language
/usr/clearos/apps/dropbox/libraries
/etc/cron.d/app-dropbox
%config(noreplace) /etc/clearos/dropbox.conf
/usr/sbin/dropboxconf
