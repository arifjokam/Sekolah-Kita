#!/usr/bin/env bash
# Resolve Playwright's Chromium shared-library dependencies without root.
#
# Playwright's Chromium is not fully self-contained: it links against ~18 desktop
# libraries (glib, nss, atk, cairo, pango, cups, xkbcommon, gbm, avahi, ...). On a
# normal desktop or CI image they are present. On a locked-down container -- a
# Termux/Ubuntu-PRoot box with no `sudo`, which is how this project is developed --
# they are not, and Chromium exits with "error while loading shared libraries"
# before a single application assertion runs.
#
# `apt-get install` needs root, but `apt-get download` does not. This fetches the
# owning packages, unpacks them into a private prefix, and lets
# tests/Browser/chromium-runtime.mjs point LD_LIBRARY_PATH at that prefix.
#
# Nothing outside the prefix is modified, no system state is touched, and this is
# a no-op on a machine where the libraries already resolve.
#
# Usage: bash tests/Browser/prepare-chromium.sh
set -uo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PREFIX=${CHROMIUM_LIB_PREFIX:-$SCRIPT_DIR/../../storage/framework/testing/chromium-libs}
DEBS=${CHROMIUM_DEB_DIR:-$PREFIX/../.chromium-debs}
LIB_SUBDIR=aarch64-linux-gnu
[ -d /usr/lib/x86_64-linux-gnu ] && LIB_SUBDIR=x86_64-linux-gnu

mkdir -p "$PREFIX" "$DEBS" || exit 1
cd "$DEBS" || exit 1

# Every Chromium binary that must start, newest revision first.
CHROME_BINARIES=()
for dir in "$HOME"/.cache/ms-playwright/chromium-*/chrome-linux*/chrome \
           "$HOME"/.cache/ms-playwright/chromium_headless_shell-*/chrome-headless-shell-linux*/chrome-headless-shell; do
    [ -e "$dir" ] && CHROME_BINARIES+=("$dir")
done

if [ ${#CHROME_BINARIES[@]} -eq 0 ]; then
    echo "no Playwright Chromium binary found; run: npx playwright install chromium" >&2
    exit 1
fi

# libname -> owning Debian package.
declare -A MAP=(
    [libglib-2.0.so.0]=libglib2.0-0t64
    [libgio-2.0.so.0]=libglib2.0-0t64
    [libgobject-2.0.so.0]=libglib2.0-0t64
    [libgmodule-2.0.so.0]=libglib2.0-0t64
    [libgthread-2.0.so.0]=libglib2.0-0t64
    [libglib-2.0.so.0]=libglib2.0-0
    [libXcomposite.so.1]=libxcomposite1
    [libXdamage.so.1]=libxdamage1
    [libXfixes.so.3]=libxfixes3
    [libXrandr.so.2]=libxrandr2
    [libasound.so.2]=libasound2t64
    [libasound.so.2]=libasound2
    [libatk-1.0.so.0]=libatk1.0-0t64
    [libatk-1.0.so.0]=libatk1.0-0
    [libatk-bridge-2.0.so.0]=libatk-bridge2.0-0t64
    [libatk-bridge-2.0.so.0]=libatk-bridge2.0-0
    [libatspi.so.0]=libatspi2.0-0t64
    [libatspi.so.0]=libatspi2.0-0
    [libgbm.so.1]=libgbm1
    [libnspr4.so]=libnspr4
    [libnss3.so]=libnss3
    [libnssutil3.so]=libnss3
    [libsmime3.so]=libnss3
    [libssl3.so]=libnss3
    [libxkbcommon.so.0]=libxkbcommon0
    [libdrm.so.2]=libdrm2
    [libwayland-server.so.0]=libwayland-server0
    [libwayland-client.so.0]=libwayland-client0
    [libwayland-egl.so.1]=libwayland-egl1
    [libexpat.so.1]=libexpat1
    [libffi.so.8]=libffi8
    [libpcre2-8.so.0]=libpcre2-8-0
    [libselinux.so.1]=libselinux1
    [libmount.so.1]=libmount1
    [libblkid.so.1]=libblkid1
    [libsystemd.so.0]=libsystemd0
    [libcap.so.2]=libcap2
    [libgcrypt.so.20]=libgcrypt20
    [libgpg-error.so.0]=libgpg-error0
    [liblzma.so.5]=liblzma5
    [libzstd.so.1]=libzstd1
    [libudev.so.1]=libudev1
    [libXrender.so.1]=libxrender1
    [libXext.so.6]=libxext6
    [libX11.so.6]=libx11-6
    [libX11-xcb.so.1]=libx11-xcb1
    [libxcb.so.1]=libxcb1
    [libxcb-dri3.so.0]=libxcb-dri3-0
    [libxcb-render.so.0]=libxcb-render0
    [libxcb-shm.so.0]=libxcb-shm0
    [libxcb-sync.so.1]=libxcb-sync1
    [libxcb-xfixes.so.0]=libxcb1
    [libxcb-glx.so.0]=libxcb-glx0
    [libXau.so.6]=libxau6
    [libXdmcp.so.6]=libxdmcp6
    [libXres.so.1]=libxres1
    [libXRes.so.1]=libxres1
    [libXcursor.so.1]=libxcursor1
    [libXi.so.6]=libxi6
    [libXtst.so.6]=libxtst6
    [libXss.so.1]=libxss1
    [libcairo.so.2]=libcairo2
    [libcairo-gobject.so.2]=libcairo2
    [libpango-1.0.so.0]=libpango-1.0-0
    [libpangocairo-1.0.so.0]=libpango-1.0-0
    [libpangoft2-1.0.so.0]=libpango-1.0-0
    [libcups.so.2]=libcups2t64
    [libcups.so.2]=libcups2
    [libgdk_pixbuf-2.0.so.0]=libgdk-pixbuf-2.0-0
    [libharfbuzz.so.0]=libharfbuzz0b
    [libfribidi.so.0]=libfribidi0
    [libthai.so.0]=libthai0
    [libthai-data.so.2]=libthai0
    [libdatrie.so.1]=libdatrie1
    [libpixman-1.so.0]=libpixman-1-0
    [libfontconfig.so.1]=libfontconfig1
    [libfreetype.so.6]=libfreetype6
    [libpng16.so.16]=libpng16-16t64
    [libjpeg.so.8]=libjpeg-turbo8
    [libbrotlidec.so.1]=libbrotli1
    [libbrotlicommon.so.1]=libbrotli1
    [libgraphite2.so.3]=libgraphite2-3
    [libbsd.so.0]=libbsd0
    [libmd.so.0]=libmd0
    [libavahi-client.so.3]=libavahi-client3
    [libavahi-common.so.3]=libavahi-common3
    [libdbus-1.so.3]=libdbus-1-3
    [libepoxy.so.0]=libepoxy0
    [libgio-2.0.so.0]=libglib2.0-0t64
)

LIBPATH="$PREFIX/usr/lib/$LIB_SUBDIR:$PREFIX/lib/$LIB_SUBDIR:$PREFIX/usr/lib:$PREFIX/lib"
export LD_LIBRARY_PATH="$LIBPATH${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"

# Unpack an already-downloaded archive if present, otherwise fetch it. The
# download step needs a writable cwd, which is why it runs inside $DEBS.
fetch() {
    local pkg=$1
    local seen=0
    for deb in "${pkg}"*.deb; do
        [ -e "$deb" ] || continue
        seen=1
        dpkg-deb -x "$deb" "$PREFIX" 2>/dev/null
    done
    [ "$seen" = "1" ] && return 0
    apt-get download "$pkg" >/dev/null 2>&1 || return 1
    for deb in "${pkg}"*.deb; do
        [ -e "$deb" ] || continue
        dpkg-deb -x "$deb" "$PREFIX" 2>/dev/null
    done
    return 0
}

unresolved=""

for round in 1 2 3 4 5 6 7 8; do
    missing=$(for bin in "${CHROME_BINARIES[@]}"; do
        ldd "$bin" 2>/dev/null | awk '/not found/{print $1}'
    done | sort -u)

    if [ -z "$missing" ]; then
        echo "round $round: all shared libraries resolved"
        break
    fi

    echo "round $round: $(echo "$missing" | wc -l) missing -> $(echo "$missing" | tr '\n' ' ')"
    for lib in $missing; do
        pkg=${MAP[$lib]:-}
        if [ -z "$pkg" ]; then
            echo "  !! no package mapping for $lib"
            unresolved="$unresolved $lib"
            continue
        fi
        if fetch "$pkg"; then
            echo "  ok $lib <- $pkg"
        else
            echo "  FAIL $lib <- $pkg (package not found)"
            unresolved="$unresolved $lib"
        fi
    done
done

echo
echo "=== final check ==="
for bin in "${CHROME_BINARIES[@]}"; do
    echo "-- $bin"
    ldd "$bin" 2>/dev/null | grep "not found" | sort -u || echo "   no missing libraries"
done

if [ -n "$(echo "$unresolved" | tr -d ' ')" ]; then
    echo
    echo "unresolved:$unresolved" >&2
    exit 1
fi

echo
echo "export LD_LIBRARY_PATH=$LIBPATH"
