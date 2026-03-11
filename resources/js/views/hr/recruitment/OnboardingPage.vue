<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Recruitment', disabled: false, href: '#' },
  { title: 'Onboarding', disabled: true, href: '#' }
];

const loading = ref(false);
const activeTab = ref('list');
const onboardings = ref<any[]>([]);
const board = ref<any[]>([]);
const boardLoading = ref(false);
const stats = ref({ not_started: 0, in_progress: 0, completed: 0, overdue: 0 });
const deptOptions = ref<string[]>([]);
const templatesList = ref<any[]>([]);
const employeeOptions = ref<any[]>([]);
const templateOptions = ref<any[]>([]);

const filters = reactive({
  search: '',
  department: '',
  status: ''
});

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0
});

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

const headers = [
  { title: 'Employee', key: 'employee', sortable: false },
  { title: 'Template', key: 'template', sortable: false },
  { title: 'Buddy', key: 'buddy', sortable: false },
  { title: 'Start Date', key: 'start_date', sortable: false },
  { title: 'Expected End', key: 'expected_end', sortable: false },
  { title: 'Progress', key: 'progress', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false }
];

const statusOptions = ['Not Started', 'In Progress', 'Completed', 'Overdue'];

const tasksDrawer = ref(false);
const selectedOb = ref<any>(null);
const obDetail = ref<any>(null);
const tasksLoading = ref(false);

const buddyDialog = ref(false);
const buddySaving = ref(false);
const buddyForm = reactive({
  buddy_id: null as number | null
});

const startDialog = ref(false);
const startSaving = ref(false);
const startForm = reactive({
  employee_id: null as number | null,
  template_id: null as number | null,
  start_date: new Date().toISOString().split('T')[0],
  buddy_id: null as number | null,
  notes: ''
});

const templatesDialog = ref(false);
const templatesLoading = ref(false);

async function fetchEmployeeOptions() {
  const { data } = await axios.get('/api/hr/employees', {
    params: { status: 'Active', per_page: 1000 }
  });

  employeeOptions.value = (data?.employees?.data ?? []).map((e: any) => ({
    id: e.id,
    name: `${e.full_name ?? `${e.first_name ?? ''} ${e.last_name ?? ''}`.trim()} (${e.employee_id ?? '-'})`
  }));
}

async function fetchTemplateOptions() {
  const { data } = await axios.get('/api/hr/onboarding-templates');
  templatesList.value = data?.templates ?? [];
  templateOptions.value = templatesList.value.filter((t: any) => t.status === 'Active');
}

async function fetchOnboardings() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/onboardings', {
      params: {
        search: filters.search || undefined,
        department: filters.department || undefined,
        status: filters.status || undefined,
        page: pagination.page,
        per_page: pagination.perPage
      }
    });

    onboardings.value = data?.onboardings?.data ?? [];
    pagination.total = data?.onboardings?.total ?? 0;
    stats.value = data?.stats ?? stats.value;
    deptOptions.value = data?.filters?.departments ?? [];
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to load onboardings.',
      color: 'error'
    };
  } finally {
    loading.value = false;
  }
}

async function fetchBoard() {
  boardLoading.value = true;
  try {
    const { data } = await axios.get('/api/hr/onboardings/board', {
      params: {
        department: filters.department || undefined
      }
    });
    board.value = data?.board ?? [];
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to load board view.',
      color: 'error'
    };
  } finally {
    boardLoading.value = false;
  }
}

function handleTableOptions(opts: any) {
  pagination.page = opts.page;
  pagination.perPage = opts.itemsPerPage;
  fetchOnboardings();
}

function statusColor(status: string) {
  if (status === 'Completed') return 'success';
  if (status === 'In Progress') return 'primary';
  if (status === 'Overdue') return 'error';
  return 'default';
}

async function openTasksDrawer(ob: any) {
  tasksDrawer.value = true;
  selectedOb.value = ob;
  tasksLoading.value = true;
  try {
    const { data } = await axios.get(`/api/hr/onboardings/${ob.id}`);
    obDetail.value = data?.onboarding ?? null;
  } catch {
    obDetail.value = null;
    snackbar.value = {
      show: true,
      message: 'Failed to load onboarding details.',
      color: 'error'
    };
  } finally {
    tasksLoading.value = false;
  }
}

async function toggleTask(task: any, newStatus: string) {
  if (!selectedOb.value?.id) return;

  try {
    const { data } = await axios.patch(
      `/api/hr/onboardings/${selectedOb.value.id}/tasks/${task.id}`,
      { status: newStatus }
    );

    const res = await axios.get(`/api/hr/onboardings/${selectedOb.value.id}`);
    obDetail.value = res.data?.onboarding ?? null;

    await fetchOnboardings();
    if (activeTab.value === 'board') await fetchBoard();

    snackbar.value = {
      show: true,
      message: data?.message ?? 'Task updated.',
      color: 'success'
    };
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to update task.',
      color: 'error'
    };
  }
}

async function openBuddyDialog(ob: any) {
  selectedOb.value = ob;
  buddyForm.buddy_id = ob?.buddy?.id ?? null;
  buddyDialog.value = true;

  if (!employeeOptions.value.length) {
    try {
      await fetchEmployeeOptions();
    } catch {
      snackbar.value = {
        show: true,
        message: 'Failed to load employee options.',
        color: 'error'
      };
    }
  }
}

async function saveBuddy() {
  if (!selectedOb.value?.id) return;
  buddySaving.value = true;
  try {
    const { data } = await axios.patch(`/api/hr/onboardings/${selectedOb.value.id}/buddy`, {
      buddy_id: buddyForm.buddy_id
    });
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Buddy updated.',
      color: 'success'
    };
    buddyDialog.value = false;
    await fetchOnboardings();
    if (activeTab.value === 'board') await fetchBoard();
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to assign buddy.',
      color: 'error'
    };
  } finally {
    buddySaving.value = false;
  }
}

async function askDelete(ob: any) {
  if (!confirm('Delete this onboarding record?')) return;
  try {
    const { data } = await axios.delete(`/api/hr/onboardings/${ob.id}`);
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Onboarding record deleted.',
      color: 'success'
    };
    await fetchOnboardings();
    if (activeTab.value === 'board') await fetchBoard();
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to delete onboarding.',
      color: 'error'
    };
  }
}

async function openStartDialog() {
  startDialog.value = true;
  startForm.employee_id = null;
  startForm.template_id = null;
  startForm.start_date = new Date().toISOString().split('T')[0];
  startForm.buddy_id = null;
  startForm.notes = '';

  try {
    await Promise.all([fetchEmployeeOptions(), fetchTemplateOptions()]);
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to load start options.',
      color: 'error'
    };
  }
}

async function saveStartOnboarding() {
  startSaving.value = true;
  try {
    const { data } = await axios.post('/api/hr/onboardings', startForm);
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Onboarding started.',
      color: 'success'
    };
    startDialog.value = false;
    await fetchOnboardings();
    if (activeTab.value === 'board') await fetchBoard();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed to start onboarding.',
      color: 'error'
    };
  } finally {
    startSaving.value = false;
  }
}

async function openTemplatesDialog() {
  templatesDialog.value = true;
  templatesLoading.value = true;
  try {
    await fetchTemplateOptions();
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to load templates.',
      color: 'error'
    };
  } finally {
    templatesLoading.value = false;
  }
}

watch(
  () => [filters.search, filters.department, filters.status],
  async () => {
    pagination.page = 1;
    await fetchOnboardings();
    if (activeTab.value === 'board') await fetchBoard();
  }
);

watch(
  () => activeTab.value,
  async (tab) => {
    if (tab === 'board') await fetchBoard();
  }
);

onMounted(async () => {
  await fetchOnboardings();
});
</script>

<template>
  <BaseBreadcrumb title="Onboarding" subtitle="Manage onboarding plans" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center ga-2 mb-4 flex-wrap">
    <div>
      <h2 class="text-h4 mb-1">Onboarding</h2>
      <p class="text-medium-emphasis mb-0">Track new hire onboarding and progress.</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-clipboard-list" @click="openTemplatesDialog">
        Manage Templates
      </v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openStartDialog">
        Start Onboarding
      </v-btn>
    </div>
  </div>

  <v-row class="mb-1">
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined">
        <v-card-text>Not Started: <strong>{{ stats.not_started }}</strong></v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined">
        <v-card-text>In Progress: <strong>{{ stats.in_progress }}</strong></v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined">
        <v-card-text>Completed: <strong>{{ stats.completed }}</strong></v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined">
        <v-card-text>Overdue: <strong>{{ stats.overdue }}</strong></v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card variant="outlined">
    <v-tabs v-model="activeTab" color="primary" class="px-4 pt-2">
      <v-tab value="list">Onboarding List</v-tab>
      <v-tab value="board">Board View</v-tab>
    </v-tabs>
    <v-divider />

    <v-window v-model="activeTab">
      <v-window-item value="list">
        <div class="pa-4">
          <v-row class="mb-2">
            <v-col cols="12" md="4">
              <v-text-field v-model="filters.search" label="Search employee" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="filters.department"
                :items="[{ title: 'All Departments', value: '' }, ...deptOptions.map((d) => ({ title: d, value: d }))]"
                label="Department"
                variant="outlined"
                hide-details
              />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="filters.status"
                :items="[{ title: 'All Statuses', value: '' }, ...statusOptions.map((s) => ({ title: s, value: s }))]"
                label="Status"
                variant="outlined"
                hide-details
              />
            </v-col>
          </v-row>

          <v-data-table-server
            :headers="headers"
            :items="onboardings"
            :loading="loading"
            :items-length="pagination.total"
            :items-per-page="pagination.perPage"
            :page="pagination.page"
            :items-per-page-options="[10, 25, 50]"
            @update:options="handleTableOptions"
          >
            <template #item.employee="{ item: ob }">
              <div v-if="ob.employee" class="d-flex align-center ga-2">
                <v-avatar size="32" color="primary" variant="tonal">
                  <v-img v-if="ob.employee.avatar" :src="ob.employee.avatar" />
                  <span v-else class="text-caption">{{ ob.employee.initials }}</span>
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-medium">{{ ob.employee.name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ ob.employee.department }}</div>
                </div>
              </div>
              <span v-else class="text-medium-emphasis">-</span>
            </template>

            <template #item.template="{ item: ob }">
              <span>{{ ob.template?.name ?? '-' }}</span>
            </template>

            <template #item.buddy="{ item: ob }">
              <span>{{ ob.buddy?.name ?? '-' }}</span>
            </template>

            <template #item.start_date="{ item: ob }">
              <span class="text-body-2">{{ ob.start_date }}</span>
            </template>

            <template #item.expected_end="{ item: ob }">
              <span :class="ob.is_overdue ? 'text-error font-weight-medium' : ''">
                {{ ob.expected_end ?? '-' }}
                <v-icon v-if="ob.is_overdue" size="14" color="error">mdi-alert-circle</v-icon>
              </span>
            </template>

            <template #item.progress="{ item: ob }">
              <div class="d-flex align-center ga-2">
                <v-progress-linear :model-value="ob.progress ?? 0" height="8" rounded class="flex-grow-1" />
                <span class="text-caption">{{ ob.progress ?? 0 }}%</span>
              </div>
            </template>

            <template #item.status="{ item: ob }">
              <v-chip :color="statusColor(ob.status)" variant="tonal" size="small">{{ ob.status }}</v-chip>
            </template>

            <template #item.actions="{ item: ob }">
              <v-menu>
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon variant="text" size="small">
                    <img src="/assets/images/icons/action-menu.svg" alt="Actions" class="action-menu-icon" />
                  </v-btn>
                </template>
                <v-list density="compact">
                  <v-list-item prepend-icon="mdi-eye" title="View Tasks" @click="openTasksDrawer(ob)" />
                  <v-list-item prepend-icon="mdi-account-heart" title="Assign Buddy" @click="openBuddyDialog(ob)" />
                  <v-list-item
                    prepend-icon="mdi-account"
                    title="View Employee"
                    @click="ob.employee?.id && router.visit('/hr/employees/' + ob.employee.id)"
                  />
                  <v-divider />
                  <v-list-item prepend-icon="mdi-delete" title="Delete" base-color="error" @click="askDelete(ob)" />
                </v-list>
              </v-menu>
            </template>
          </v-data-table-server>
        </div>
      </v-window-item>

      <v-window-item value="board">
        <div class="pa-4">
          <div v-if="boardLoading" class="text-center py-10">
            <v-progress-circular indeterminate />
          </div>
          <div v-else class="d-flex ga-4 overflow-x-auto pb-4">
            <div v-for="col in board" :key="col.status" style="min-width: 260px; width: 260px">
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center ga-2">
                  <v-icon size="14" :color="col.color">mdi-circle</v-icon>
                  <span class="text-body-2 font-weight-bold">{{ col.status }}</span>
                </div>
                <v-chip size="x-small" :color="col.color" variant="tonal">{{ col.count }}</v-chip>
              </div>

              <div class="d-flex flex-column ga-2">
                <v-card
                  v-for="item in col.items"
                  :key="item.id"
                  variant="outlined"
                  class="pa-3 cursor-pointer"
                  @click="openTasksDrawer(item)"
                >
                  <div class="d-flex align-center ga-2 mb-2">
                    <v-avatar size="28" :color="col.color" variant="tonal">
                      <span class="text-caption">{{ item.employee?.initials }}</span>
                    </v-avatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">{{ item.employee?.name }}</div>
                      <div class="text-caption text-medium-emphasis">{{ item.template }}</div>
                    </div>
                  </div>

                  <v-progress-linear :model-value="item.progress" :color="col.color" height="4" rounded class="mb-2" />

                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-medium-emphasis">{{ item.tasks }} tasks</span>
                    <span class="text-caption" :class="item.is_overdue ? 'text-error' : 'text-medium-emphasis'">
                      Due {{ item.expected_end ?? '-' }}
                    </span>
                  </div>

                  <div v-if="item.buddy" class="text-caption text-medium-emphasis mt-1">Buddy: {{ item.buddy }}</div>
                </v-card>

                <div
                  v-if="col.items.length === 0"
                  class="text-center py-8 text-medium-emphasis text-caption border rounded"
                >
                  No records
                </div>
              </div>
            </div>
          </div>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-navigation-drawer v-model="tasksDrawer" location="right" width="480" temporary>
    <div class="pa-4">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <h3 class="text-h6">Onboarding Tasks</h3>
          <p class="text-body-2 text-medium-emphasis">{{ obDetail?.employee?.name ?? '-' }}</p>
        </div>
        <v-btn icon variant="text" @click="tasksDrawer = false">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>

      <v-card variant="tonal" color="primary" class="mb-4 pa-3">
        <div class="d-flex justify-space-between align-center mb-2">
          <span class="text-body-2 font-weight-medium">Progress</span>
          <span class="text-h6 font-weight-bold">{{ obDetail?.progress ?? 0 }}%</span>
        </div>
        <v-progress-linear
          :model-value="obDetail?.progress ?? 0"
          color="primary"
          bg-color="white"
          height="8"
          rounded
        />
        <div class="text-caption mt-1">{{ obDetail?.tasks_done ?? 0 }} / {{ obDetail?.tasks_total ?? 0 }} tasks done</div>
      </v-card>

      <v-list v-if="!tasksLoading">
        <v-list-item
          v-for="task in obDetail?.tasks ?? []"
          :key="task.id"
          class="mb-2 rounded border px-3 py-2"
        >
          <template #prepend>
            <v-checkbox-btn
              :model-value="task.status === 'Completed'"
              :color="task.status === 'Completed' ? 'success' : 'default'"
              @update:model-value="toggleTask(task, task.status === 'Completed' ? 'Pending' : 'Completed')"
            />
          </template>

          <v-list-item-title
            :class="task.status === 'Completed' ? 'text-decoration-line-through text-medium-emphasis' : ''"
          >
            {{ task.title }}
          </v-list-item-title>
          <v-list-item-subtitle>
            <v-chip size="x-small" variant="tonal" class="mr-1">{{ task.category }}</v-chip>
            <span class="text-caption">Due: {{ task.due_date }}</span>
          </v-list-item-subtitle>

          <template #append>
            <v-chip v-if="task.status === 'Completed'" size="x-small" color="success" variant="flat">Done</v-chip>
            <v-chip v-else-if="task.required" size="x-small" color="error" variant="tonal">Required</v-chip>
          </template>
        </v-list-item>
      </v-list>

      <div v-else class="text-center py-8">
        <v-progress-circular indeterminate />
      </div>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="buddyDialog" max-width="500">
    <v-card>
      <v-card-title class="text-h6">Assign Buddy</v-card-title>
      <v-card-text>
        <v-autocomplete
          v-model="buddyForm.buddy_id"
          :items="employeeOptions"
          item-title="name"
          item-value="id"
          clearable
          label="Select Buddy"
          variant="outlined"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="buddyDialog = false">Cancel</v-btn>
        <v-btn color="primary" :loading="buddySaving" @click="saveBuddy">Save</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="startDialog" max-width="620">
    <v-card>
      <v-card-title class="text-h6">Start Onboarding</v-card-title>
      <v-card-text>
        <v-autocomplete
          v-model="startForm.employee_id"
          :items="employeeOptions"
          item-title="name"
          item-value="id"
          label="Employee *"
          variant="outlined"
          class="mb-3"
        />
        <v-select
          v-model="startForm.template_id"
          :items="templateOptions"
          item-title="name"
          item-value="id"
          label="Template *"
          variant="outlined"
          class="mb-3"
        />
        <v-text-field v-model="startForm.start_date" type="date" label="Start Date *" variant="outlined" class="mb-3" />
        <v-autocomplete
          v-model="startForm.buddy_id"
          :items="employeeOptions"
          item-title="name"
          item-value="id"
          clearable
          label="Buddy"
          variant="outlined"
          class="mb-3"
        />
        <v-textarea v-model="startForm.notes" label="Notes" rows="3" variant="outlined" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="startDialog = false">Cancel</v-btn>
        <v-btn color="primary" :loading="startSaving" @click="saveStartOnboarding">Start</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="templatesDialog" max-width="860">
    <v-card>
      <v-card-title class="text-h6">Onboarding Templates</v-card-title>
      <v-card-text>
        <div v-if="templatesLoading" class="text-center py-8">
          <v-progress-circular indeterminate />
        </div>
        <v-table v-else density="compact">
          <thead>
            <tr>
              <th>Name</th>
              <th>Status</th>
              <th>Days</th>
              <th>Tasks</th>
              <th>Used</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tpl in templatesList" :key="tpl.id">
              <td>{{ tpl.name }}</td>
              <td>{{ tpl.status }}</td>
              <td>{{ tpl.days_to_complete }}</td>
              <td>{{ tpl.tasks_count }}</td>
              <td>{{ tpl.onboardings_count }}</td>
            </tr>
            <tr v-if="!templatesList.length">
              <td colspan="5" class="text-center text-medium-emphasis py-6">No templates found.</td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="templatesDialog = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
