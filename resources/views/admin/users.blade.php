<x-app-layout>
    @php
        $adminCount = $users->filter(fn($u) => $u->isAdmin())->count();
        $creatorCount = $users->filter(fn($u) => $u->hasRole('creator') || $u->hasRole('user'))->count();
    @endphp

    <x-pp-page-header title="Manage Users" description="Manage permissions, invite teammates and track activity across your social ecosystem.">
        <x-slot name="breadcrumb">
            <span>Organization</span><span>/</span><span class="text-[#004ac6]">Team Management</span>
        </x-slot>
        <x-slot name="actions">
            <button type="button" id="ppInviteOpenBtn" class="pp-btn-primary">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                Invite User
            </button>
        </x-slot>
    </x-pp-page-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="pp-card p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-[#004ac6]/10 rounded-full flex items-center justify-center text-[#004ac6]">
                <span class="material-symbols-outlined">group</span>
            </div>
            <div>
                <p class="text-xs font-semibold text-[#737686] uppercase tracking-wide">Total Users</p>
                <p class="font-display text-xl font-semibold">{{ $users->count() }}</p>
            </div>
        </div>
        <div class="pp-card p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-[#4648d4]/10 rounded-full flex items-center justify-center text-[#4648d4]">
                <span class="material-symbols-outlined">admin_panel_settings</span>
            </div>
            <div>
                <p class="text-xs font-semibold text-[#737686] uppercase tracking-wide">Admins</p>
                <p class="font-display text-xl font-semibold">{{ $adminCount }}</p>
            </div>
        </div>
        <div class="pp-card p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-[#6b6e70]/10 rounded-full flex items-center justify-center text-[#6b6e70]">
                <span class="material-symbols-outlined">brush</span>
            </div>
            <div>
                <p class="text-xs font-semibold text-[#737686] uppercase tracking-wide">Creators</p>
                <p class="font-display text-xl font-semibold">{{ $creatorCount }}</p>
            </div>
        </div>
    </div>

    <div class="pp-card overflow-hidden mb-8">
        <div class="pp-card-header flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-[#131b2e]">Active Members</h3>
            <span class="pp-status-chip bg-[#f2f3ff] text-[#434655]">{{ $users->count() }} users</span>
        </div>
        <div class="overflow-x-auto">
            <table class="pp-table w-full">
                <thead>
                    <tr>
                        <th>User Profile</th>
                        <th>Email</th>
                        <th>Connections</th>
                        <th>Posts</th>
                        <th>Role</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        @php
                            $editRole = $user->isAdmin() ? 'admin' : 'creator';
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#2563eb] text-white flex items-center justify-center font-bold text-sm">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
                                    <div>
                                        <p class="font-semibold text-sm text-[#131b2e]">{{ $user->name }}</p>
                                        <p class="text-[11px] text-[#737686]">Joined {{ $user->created_at->format('M d, Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-[#434655]">{{ $user->email }}</td>
                            <td class="text-[#434655]">{{ $user->socialAccounts->count() }} profiles</td>
                            <td class="text-[#434655]">{{ $user->posts->count() }} posts</td>
                            <td>
                                @if($user->isAdmin())
                                    <span class="pp-status-chip bg-[#004ac6]/10 text-[#004ac6]">Admin</span>
                                @elseif($user->hasRole('creator'))
                                    <span class="pp-status-chip bg-[#4648d4]/10 text-[#4648d4]">Creator</span>
                                @else
                                    <span class="pp-status-chip bg-[#f2f3ff] text-[#434655]">User</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button"
                                        class="pp-edit-user-btn p-2 text-[#737686] hover:text-[#004ac6] rounded-lg transition-colors"
                                        title="Edit user"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        data-email="{{ $user->email }}"
                                        data-role="{{ $editRole }}">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    @if($user->id !== auth()->id())
                                        <form action="{{ route('admin.delete-user', $user->id) }}" method="POST"
                                            data-pp-confirm="This user account will be permanently deleted."
                                            data-pp-confirm-title="Delete user?"
                                            data-pp-confirm-variant="danger"
                                            data-pp-confirm-button="Delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 text-[#737686] hover:text-[#ba1a1a] rounded-lg transition-colors" title="Delete">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div id="panelBackdrop" class="pp-team-drawer-backdrop" aria-hidden="true"></div>

    {{-- Invite side panel — closed by default via .pp-team-drawer --}}
    <aside id="invitePanel" class="pp-team-drawer" aria-hidden="true">
        <div class="h-full flex flex-col">
            <div class="px-8 py-6 border-b border-[#c3c6d7] flex items-center justify-between">
                <div>
                    <h2 class="font-display text-xl font-semibold text-[#131b2e]">Invite User</h2>
                    <p class="text-xs text-[#737686] mt-1">Add a new teammate to your organization.</p>
                </div>
                <button type="button" data-pp-drawer-close class="p-2 hover:bg-[#f2f3ff] rounded-full text-[#737686]" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form action="{{ route('admin.users.store') }}" method="POST" class="flex-1 flex flex-col">
                @csrf
                <div class="flex-1 overflow-y-auto px-8 py-6 space-y-5">
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Full Name</label>
                        <input type="text" name="name" class="pp-input" required placeholder="John Doe" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Email Address</label>
                        <input type="email" name="email" class="pp-input" required placeholder="john@example.com" value="{{ old('email') }}">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Password</label>
                        <input type="password" name="password" class="pp-input" required placeholder="Min 8 characters">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Assigned Role</label>
                        <select name="role" class="pp-input" required>
                            <option value="creator" @selected(old('role', 'creator') === 'creator')>Creator (Schedule & Create)</option>
                            <option value="admin" @selected(old('role') === 'admin')>Admin (Full Control)</option>
                        </select>
                    </div>
                    <div class="p-4 bg-[#f2f3ff] rounded-xl border border-[#c3c6d7] flex gap-3">
                        <span class="material-symbols-outlined text-[#004ac6] text-[20px]">info</span>
                        <p class="text-sm text-[#434655] leading-snug">The user can sign in immediately with the credentials you provide.</p>
                    </div>
                </div>
                <div class="p-8 border-t border-[#c3c6d7] grid grid-cols-2 gap-4">
                    <button type="button" data-pp-drawer-close class="pp-btn-secondary">Cancel</button>
                    <button type="submit" class="pp-btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </aside>

    {{-- Edit user side panel --}}
    <aside id="editPanel" class="pp-team-drawer" aria-hidden="true">
        <div class="h-full flex flex-col">
            <div class="px-8 py-6 border-b border-[#c3c6d7] flex items-center justify-between">
                <div>
                    <h2 class="font-display text-xl font-semibold text-[#131b2e]">Edit Member</h2>
                    <p class="text-xs text-[#737686] mt-1">Update profile, password, or role.</p>
                </div>
                <button type="button" data-pp-drawer-close class="p-2 hover:bg-[#f2f3ff] rounded-full text-[#737686]" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form id="editUserForm" method="POST" class="flex-1 flex flex-col">
                @csrf
                @method('PUT')
                <div class="flex-1 overflow-y-auto px-8 py-6 space-y-5">
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Full Name</label>
                        <input type="text" name="name" id="editName" class="pp-input" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Email Address</label>
                        <input type="email" name="email" id="editEmail" class="pp-input" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">New Password</label>
                        <input type="password" name="password" id="editPassword" class="pp-input" placeholder="Leave blank to keep current">
                        <p class="text-[11px] text-[#737686] mt-1">Minimum 8 characters when changing.</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Role</label>
                        <select name="role" id="editRole" class="pp-input" required>
                            <option value="creator">Creator (Schedule & Create)</option>
                            <option value="admin">Admin (Full Control)</option>
                        </select>
                    </div>
                </div>
                <div class="p-8 border-t border-[#c3c6d7] grid grid-cols-2 gap-4">
                    <button type="button" data-pp-drawer-close class="pp-btn-secondary">Cancel</button>
                    <button type="submit" class="pp-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </aside>

    @push('scripts')
    <script>
        (function () {
            const invitePanel = document.getElementById('invitePanel');
            const editPanel = document.getElementById('editPanel');
            const backdrop = document.getElementById('panelBackdrop');
            const updateUrl = @json(url('/admin/users'));

            function closeNavSidebar() {
                document.getElementById('ppSidebar')?.classList.remove('open');
                document.getElementById('ppSidebarBackdrop')?.classList.add('hidden');
            }

            function syncBody() {
                const anyOpen = invitePanel.classList.contains('is-open') || editPanel.classList.contains('is-open');
                document.body.classList.toggle('pp-team-drawer-open', anyOpen);
                backdrop.classList.toggle('is-open', anyOpen);
                backdrop.setAttribute('aria-hidden', anyOpen ? 'false' : 'true');
            }

            function closeAllDrawers() {
                invitePanel.classList.remove('is-open');
                editPanel.classList.remove('is-open');
                invitePanel.setAttribute('aria-hidden', 'true');
                editPanel.setAttribute('aria-hidden', 'true');
                syncBody();
            }

            function openInvite() {
                closeNavSidebar();
                editPanel.classList.remove('is-open');
                editPanel.setAttribute('aria-hidden', 'true');
                invitePanel.classList.add('is-open');
                invitePanel.setAttribute('aria-hidden', 'false');
                syncBody();
            }

            function openEdit(id, name, email, role) {
                closeNavSidebar();
                invitePanel.classList.remove('is-open');
                invitePanel.setAttribute('aria-hidden', 'true');
                document.getElementById('editUserForm').action = updateUrl + '/' + id;
                document.getElementById('editName').value = name;
                document.getElementById('editEmail').value = email;
                document.getElementById('editPassword').value = '';
                document.getElementById('editRole').value = role;
                editPanel.classList.add('is-open');
                editPanel.setAttribute('aria-hidden', 'false');
                syncBody();
            }

            // Always start closed (prevents flash / stuck-open after navigation)
            closeAllDrawers();

            document.getElementById('ppInviteOpenBtn')?.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openInvite();
            });

            document.querySelectorAll('.pp-edit-user-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openEdit(btn.dataset.id, btn.dataset.name, btn.dataset.email, btn.dataset.role);
                });
            });

            document.querySelectorAll('[data-pp-drawer-close]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeAllDrawers();
                });
            });

            backdrop?.addEventListener('click', closeAllDrawers);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeAllDrawers();
            });

            @if($errors->any() && old('_token'))
                openInvite();
            @endif
        })();
    </script>
    @endpush
</x-app-layout>
