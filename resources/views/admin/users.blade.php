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
            <button type="button" onclick="toggleInvitePanel()" class="pp-btn-primary">
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
                                        onclick="openEditPanel({{ $user->id }}, @js($user->name), @js($user->email), @js($editRole))"
                                        class="p-2 text-[#737686] hover:text-[#004ac6] rounded-lg transition-colors" title="Edit user">
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

    {{-- Invite side panel --}}
    <div id="invitePanel" class="fixed top-0 right-0 h-full w-full max-w-[400px] bg-white shadow-2xl z-[60] transform translate-x-full transition-transform duration-300 border-l border-[#c3c6d7]">
        <div class="h-full flex flex-col">
            <div class="px-8 py-6 border-b border-[#c3c6d7] flex items-center justify-between">
                <div>
                    <h2 class="font-display text-xl font-semibold text-[#131b2e]">Invite User</h2>
                    <p class="text-xs text-[#737686] mt-1">Add a new teammate to your organization.</p>
                </div>
                <button type="button" onclick="toggleInvitePanel()" class="p-2 hover:bg-[#f2f3ff] rounded-full text-[#737686]">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form action="{{ route('admin.users.store') }}" method="POST" class="flex-1 flex flex-col">
                @csrf
                <div class="flex-1 overflow-y-auto px-8 py-6 space-y-5">
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Full Name</label>
                        <input type="text" name="name" class="pp-input" required placeholder="John Doe">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Email Address</label>
                        <input type="email" name="email" class="pp-input" required placeholder="john@example.com">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Password</label>
                        <input type="password" name="password" class="pp-input" required placeholder="Min 8 characters">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Assigned Role</label>
                        <select name="role" class="pp-input" required>
                            <option value="creator">Creator (Schedule & Create)</option>
                            <option value="admin">Admin (Full Control)</option>
                        </select>
                    </div>
                    <div class="p-4 bg-[#f2f3ff] rounded-xl border border-[#c3c6d7] flex gap-3">
                        <span class="material-symbols-outlined text-[#004ac6] text-[20px]">info</span>
                        <p class="text-sm text-[#434655] leading-snug">The user can sign in immediately with the credentials you provide.</p>
                    </div>
                </div>
                <div class="p-8 border-t border-[#c3c6d7] grid grid-cols-2 gap-4">
                    <button type="button" onclick="toggleInvitePanel()" class="pp-btn-secondary">Cancel</button>
                    <button type="submit" class="pp-btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit user side panel --}}
    <div id="editPanel" class="fixed top-0 right-0 h-full w-full max-w-[400px] bg-white shadow-2xl z-[60] transform translate-x-full transition-transform duration-300 border-l border-[#c3c6d7]">
        <div class="h-full flex flex-col">
            <div class="px-8 py-6 border-b border-[#c3c6d7] flex items-center justify-between">
                <div>
                    <h2 class="font-display text-xl font-semibold text-[#131b2e]">Edit Member</h2>
                    <p class="text-xs text-[#737686] mt-1">Update profile, password, or role.</p>
                </div>
                <button type="button" onclick="closeEditPanel()" class="p-2 hover:bg-[#f2f3ff] rounded-full text-[#737686]">
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
                    <button type="button" onclick="closeEditPanel()" class="pp-btn-secondary">Cancel</button>
                    <button type="submit" class="pp-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <div id="panelBackdrop" class="fixed inset-0 bg-black/20 backdrop-blur-[2px] z-[55] hidden" onclick="closeAllPanels()"></div>

    @push('scripts')
    <script>
        function toggleInvitePanel() {
            closeEditPanel();
            const panel = document.getElementById('invitePanel');
            const backdrop = document.getElementById('panelBackdrop');
            const open = panel.classList.contains('translate-x-full');
            panel.classList.toggle('translate-x-full', !open);
            backdrop.classList.toggle('hidden', !open);
        }

        function openEditPanel(id, name, email, role) {
            closeInvitePanel();
            document.getElementById('editUserForm').action = `/admin/users/${id}`;
            document.getElementById('editName').value = name;
            document.getElementById('editEmail').value = email;
            document.getElementById('editPassword').value = '';
            document.getElementById('editRole').value = role;
            const panel = document.getElementById('editPanel');
            const backdrop = document.getElementById('panelBackdrop');
            panel.classList.remove('translate-x-full');
            backdrop.classList.remove('hidden');
        }

        function closeEditPanel() {
            document.getElementById('editPanel').classList.add('translate-x-full');
            if (document.getElementById('invitePanel').classList.contains('translate-x-full')) {
                document.getElementById('panelBackdrop').classList.add('hidden');
            }
        }

        function closeInvitePanel() {
            document.getElementById('invitePanel').classList.add('translate-x-full');
            if (document.getElementById('editPanel').classList.contains('translate-x-full')) {
                document.getElementById('panelBackdrop').classList.add('hidden');
            }
        }

        function closeAllPanels() {
            closeEditPanel();
            closeInvitePanel();
            document.getElementById('panelBackdrop').classList.add('hidden');
        }
    </script>
    @endpush
</x-app-layout>
