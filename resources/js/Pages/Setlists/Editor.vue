<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-3 min-w-0">
          <Link
            :href="route('events.show', event.key)"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 flex-shrink-0"
          >
            <i class="pi pi-arrow-left text-lg" />
          </Link>
          <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-50 leading-tight truncate">
            Setlist — {{ event.title }}
          </h2>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0" v-if="canWrite">
          <Button
            icon="pi pi-sparkles"
            label="Generate"
            :loading="generating"
            :disabled="saving"
            size="small"
            class="!hidden sm:!inline-flex"
            @click="openGenerateDialog"
          />
          <Button
            icon="pi pi-sparkles"
            :loading="generating"
            :disabled="saving"
            class="sm:!hidden"
            rounded
            text
            v-tooltip.bottom="'Generate with AI'"
            @click="openGenerateDialog"
          />
          <Button
            v-if="localSetlist"
            icon="pi pi-comment"
            label="Refine"
            size="small"
            severity="secondary"
            outlined
            class="!hidden sm:!inline-flex"
            @click="refineDrawerOpen = true"
          />
          <Button
            v-if="localSetlist"
            icon="pi pi-comment"
            size="small"
            severity="secondary"
            outlined
            rounded
            class="sm:!hidden"
            v-tooltip.bottom="'Refine with AI'"
            @click="refineDrawerOpen = true"
          />
          <Button
            icon="pi pi-check"
            label="Save"
            :loading="saving"
            :disabled="generating || !localSetlist"
            size="small"
            class="!hidden sm:!inline-flex"
            @click="saveSetlist"
          />
          <Button
            icon="pi pi-check"
            :loading="saving"
            :disabled="generating || !localSetlist"
            class="sm:!hidden"
            rounded
            text
            v-tooltip.bottom="'Save'"
            @click="saveSetlist"
          />
          <Button
            icon="pi pi-print"
            label="Print"
            size="small"
            severity="secondary"
            outlined
            class="!hidden sm:!inline-flex print:hidden"
            @click="printSetlist"
          />
          <Button
            icon="pi pi-print"
            severity="secondary"
            outlined
            class="sm:!hidden print:hidden"
            rounded
            v-tooltip.bottom="'Print'"
            @click="printSetlist"
          />
          <Link :href="route('setlists.live', event.key)">
            <Button
              icon="pi pi-play"
              label="Go Live"
              severity="danger"
              size="small"
              class="!hidden sm:!inline-flex"
            />
            <Button
              icon="pi pi-play"
              severity="danger"
              class="sm:!hidden"
              rounded
              v-tooltip.bottom="'Go Live'"
            />
          </Link>
        </div>
      </div>
    </template>

    <Container>
      <div id="setlist-print-area">
      <!-- Print-only header -->
      <div class="hidden print:block mb-3">
        <h1 class="text-xl font-bold text-gray-900">{{ event.title }} — Setlist</h1>
        <div class="flex gap-3 text-xs text-gray-500 mt-0.5">
          <span v-if="event.type">{{ event.type.name }}</span>
          <span>{{ formatDate(event.date) }}</span>
          <span v-if="event.time">{{ formatTime(event.time) }}</span>
          <span v-if="localSetlist">{{ songCount }} songs<span v-if="totalDuration"> · ~{{ totalDuration }} min</span></span>
        </div>
      </div>

      <!-- Event summary -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-4 mb-4 print:hidden">
        <div class="flex flex-wrap gap-3 text-sm text-gray-600 dark:text-gray-400">
          <span v-if="event.type"><i class="pi pi-tag mr-1" />{{ event.type.name }}</span>
          <span><i class="pi pi-calendar mr-1" />{{ formatDate(event.date) }}</span>
          <span v-if="event.time"><i class="pi pi-clock mr-1" />{{ formatTime(event.time) }}</span>
          <span v-if="event.roster_members?.length">
            <i class="pi pi-users mr-1" />{{ event.roster_members.length }} musicians
          </span>
        </div>
        <div
          v-if="event.notes"
          class="mt-2 text-xs text-gray-500 dark:text-gray-400 line-clamp-2"
          v-html="event.notes"
        />
      </div>

      <!-- AI Sources panel (shown after generation) -->
      <div
        v-if="localSetlist && (localSetlist.event_context || localSetlist.image_context?.length)"
        class="bg-white dark:bg-slate-800 rounded-lg shadow mb-4 print:hidden overflow-hidden"
      >
        <button
          class="w-full flex items-center justify-between px-4 py-3 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
          @click="sourcesOpen = !sourcesOpen"
        >
          <span class="flex items-center gap-2">
            <i class="pi pi-eye text-xs" />
            AI sources — what the generator saw
          </span>
          <i :class="['pi text-xs transition-transform', sourcesOpen ? 'pi-chevron-up' : 'pi-chevron-down']" />
        </button>

        <div v-if="sourcesOpen" class="border-t border-gray-100 dark:border-gray-700 px-4 py-3 flex flex-col gap-4">
          <!-- Event context -->
          <div v-if="localSetlist.event_context">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Event details</p>
            <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans leading-relaxed">{{ localSetlist.event_context }}</pre>
          </div>

          <!-- Image context -->
          <div v-if="localSetlist.image_context?.length">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
              Attachments ({{ localSetlist.image_context.length }})
            </p>
            <div class="flex flex-col gap-3">
              <div
                v-for="(img, i) in localSetlist.image_context"
                :key="i"
                class="rounded-md bg-gray-50 dark:bg-slate-700 p-3"
              >
                <div class="flex items-center gap-2 mb-1">
                  <span :class="['text-xs font-medium px-1.5 py-0.5 rounded', imageTypeClass(img.type)]">
                    {{ imageTypeLabel(img.type) }}
                  </span>
                  <span class="text-xs text-gray-400 dark:text-gray-500">Image {{ i + 1 }}</span>
                </div>
                <pre v-if="img.content" class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans leading-relaxed mt-1">{{ img.content }}</pre>
                <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic mt-1">No structured extraction — used as general context only.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No setlist yet -->
      <div
        v-if="!localSetlist && !generating"
        class="bg-white dark:bg-slate-800 rounded-lg shadow p-8 sm:p-12 text-center"
      >
        <i class="pi pi-list-check text-5xl text-gray-300 dark:text-gray-600 mb-4" />
        <p class="text-gray-500 dark:text-gray-400 mb-4">No setlist yet.</p>
        <Button
          v-if="canWrite"
          label="Generate with AI"
          icon="pi pi-sparkles"
          @click="generateSetlist"
        />
      </div>

      <!-- Generating spinner -->
      <div
        v-else-if="generating"
        class="bg-white dark:bg-slate-800 rounded-lg shadow p-8 sm:p-12 text-center"
      >
        <i class="pi pi-spin pi-spinner text-4xl text-blue-500 mb-4" />
        <p class="text-gray-500 dark:text-gray-400">AI is building your setlist…</p>
      </div>

      <!-- Setlist editor -->
      <div v-else class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
        <!-- Status bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700 print:hidden">
          <div class="flex items-center gap-2 flex-wrap min-w-0">
            <Tag
              :value="localSetlist.status === 'ready' ? 'Ready' : 'Draft'"
              :severity="localSetlist.status === 'ready' ? 'success' : 'secondary'"
            />
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ songCount }} songs
              <span v-if="totalDuration"> · ~{{ totalDuration }} min</span>
            </span>
            <span v-if="localSetlist.generated_at" class="text-xs text-gray-400 dark:text-gray-500 hidden sm:inline">
              · AI generated {{ formatRelative(localSetlist.generated_at) }}
            </span>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0" v-if="canWrite">
            <Button
              v-if="localSetlist.status === 'draft'"
              label="Mark Ready"
              size="small"
              severity="success"
              outlined
              class="!hidden sm:!inline-flex"
              @click="markReady"
            />
            <Button
              v-if="localSetlist.status === 'draft'"
              icon="pi pi-check-circle"
              size="small"
              severity="success"
              outlined
              class="sm:!hidden"
              v-tooltip.bottom="'Mark Ready'"
              @click="markReady"
            />
            <Button
              label="Add Song"
              icon="pi pi-plus"
              size="small"
              outlined
              class="!hidden sm:!inline-flex"
              @click="openAddDialog"
            />
            <Button
              icon="pi pi-plus"
              size="small"
              outlined
              class="sm:!hidden"
              v-tooltip.bottom="'Add Song'"
              @click="openAddDialog"
            />
            <Button
              label="Add Break"
              icon="pi pi-pause"
              size="small"
              severity="warning"
              outlined
              class="!hidden sm:!inline-flex"
              @click="addBreak"
            />
            <Button
              icon="pi pi-pause"
              size="small"
              severity="warning"
              outlined
              class="sm:!hidden"
              v-tooltip.bottom="'Add Break'"
              @click="addBreak"
            />
            <Button
              icon="pi pi-refresh"
              size="small"
              severity="danger"
              text
              v-tooltip.top="'Clear setlist'"
              @click="confirmClear"
            />
          </div>
        </div>

        <!-- Song list -->
        <draggable
          v-model="localSetlist.songs"
          item-key="id"
          handle=".drag-handle"
          :disabled="!canWrite"
          @end="onDragEnd"
        >
          <template #item="{ element, index }">
            <div>
            <!-- Break row -->
            <div
              v-if="element.type === 'break'"
              class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-100 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20"
            >
              <span class="w-5 sm:w-6 flex-shrink-0" />
              <span
                v-if="canWrite"
                class="drag-handle cursor-move text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 flex-shrink-0"
              >
                <i class="pi pi-bars" />
              </span>
              <div class="flex-1 flex items-center gap-2 text-amber-600 dark:text-amber-400 text-sm font-medium">
                <i class="pi pi-pause" />
                <span>— SET BREAK —</span>
              </div>
              <div v-if="canWrite" class="flex-shrink-0">
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  size="small"
                  severity="danger"
                  @click="removeEntry(index)"
                />
              </div>
            </div>

            <!-- Song row -->
            <div
              v-else
              class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
            >
              <!-- Position (count only songs, not breaks) -->
              <span class="w-5 sm:w-6 text-center text-sm text-gray-400 dark:text-gray-500 flex-shrink-0">
                {{ songPosition(index) }}
              </span>

              <!-- Drag handle -->
              <span
                v-if="canWrite"
                class="drag-handle cursor-move text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 flex-shrink-0"
              >
                <i class="pi pi-bars" />
              </span>

              <!-- Song info -->
              <div class="flex-1 min-w-0">
                <div class="font-medium text-gray-900 dark:text-gray-50 truncate">
                  {{ element.title }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 flex flex-wrap gap-2">
                  <span v-if="element.artist">{{ element.artist }}</span>
                  <span v-if="element.song_key" class="text-xs bg-gray-100 dark:bg-slate-600 px-1.5 py-0.5 rounded">
                    {{ element.song_key }}
                  </span>
                  <span v-if="element.genre" class="text-xs text-gray-400">{{ element.genre }}</span>
                  <span v-if="element.bpm" class="text-xs text-gray-400">{{ element.bpm }} BPM</span>
                  <span v-if="element.energy" class="text-xs text-gray-400">E{{ element.energy }}</span>
                  <span v-if="element.lead_singer" class="text-xs text-gray-400">
                    <i class="pi pi-microphone text-xs" /> {{ element.lead_singer }}
                  </span>
                </div>
                <div v-if="element.notes" class="text-xs text-blue-500 dark:text-blue-400 mt-0.5">
                  {{ element.notes }}
                </div>
              </div>

              <!-- Actions -->
              <div v-if="canWrite" class="flex items-center gap-1 flex-shrink-0">
                <Button
                  icon="pi pi-pencil"
                  text
                  rounded
                  size="small"
                  @click="editEntry(element, index)"
                />
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  size="small"
                  severity="danger"
                  @click="removeEntry(index)"
                />
              </div>
            </div>
            </div>
          </template>
        </draggable>

        <div
          v-if="localSetlist.songs.length === 0"
          class="text-center py-10 text-gray-400 dark:text-gray-500"
        >
          No songs yet. Add songs or generate with AI.
        </div>
      </div>
      </div><!-- #setlist-print-area -->
    </Container>

    <!-- AI generate dialog -->
    <Dialog
      v-model:visible="generateDialogVisible"
      header="Generate Setlist with AI"
      :style="{ width: 'min(480px, 95vw)' }"
      modal
    >
      <div class="flex flex-col gap-3 pt-2">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          AI will build a setlist using the event type, roster, notes, and your song library.
          Add any extra context below.
        </p>

        <!-- Saved prompts -->
        <div v-if="promptTemplates.length > 0">
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-1.5">Saved prompts — click to load</p>
          <div class="flex flex-wrap gap-2 max-h-24 overflow-y-auto">
            <button
              v-for="tpl in promptTemplates"
              :key="tpl.id"
              :class="[
                'inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full border transition-colors',
                selectedGenerateTemplate?.id === tpl.id
                  ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                  : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600'
              ]"
              :aria-label="tpl.name"
              @click="loadGenerateTemplate(tpl)"
            >
              <i class="pi pi-bookmark text-xs" />
              {{ tpl.name }}
              <span
                class="ml-0.5 text-gray-400 hover:text-red-500 transition-colors"
                :aria-label="`Remove ${tpl.name}`"
                @click.stop="deleteTemplate(tpl)"
              >
                <i class="pi pi-times text-xs" />
              </span>
            </button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Additional context <span class="text-gray-400 font-normal">(optional)</span>
          </label>
          <Textarea
            ref="generateTextarea"
            v-model="aiContext"
            class="w-full"
            rows="4"
            placeholder="e.g. Keep energy high throughout. The bride hates country music. End with something slow."
            auto-resize
          />
          <!-- Loaded-from indicator -->
          <div v-if="selectedGenerateTemplate" class="flex items-center gap-1 mt-1 text-xs text-gray-500 dark:text-gray-400">
            <i class="pi pi-bookmark text-xs" />
            Loaded from: <span class="font-medium">{{ selectedGenerateTemplate.name }}</span>
            <button class="ml-1 hover:text-gray-700 dark:hover:text-gray-200" @click="clearGenerateTemplate">clear</button>
          </div>
        </div>

        <!-- Save actions -->
        <div v-if="aiContext.trim()" class="flex flex-col gap-2">
          <!-- Loaded from a template: Update or Save as new -->
          <div v-if="selectedGenerateTemplate" class="flex items-center gap-2 flex-wrap">
            <Button
              :label="`Update &quot;${selectedGenerateTemplate.name}&quot;`"
              icon="pi pi-bookmark"
              size="small"
              :loading="savingTemplate"
              @click="updateTemplate('generate')"
            />
            <Button
              label="Save as new…"
              size="small"
              severity="secondary"
              outlined
              @click="showSaveAsGenerate = !showSaveAsGenerate"
            />
          </div>
          <!-- Fresh text: Save prompt -->
          <div v-else class="flex items-center gap-2">
            <InputText
              v-model="newTemplateName"
              class="flex-1"
              size="small"
              placeholder="Template name…"
            />
            <Button
              label="Save prompt"
              icon="pi pi-bookmark"
              size="small"
              severity="secondary"
              outlined
              :disabled="!newTemplateName.trim()"
              :loading="savingTemplate"
              @click="saveTemplate('generate')"
            />
          </div>
          <!-- Save as new name field (only when loaded template + expanded) -->
          <div v-if="selectedGenerateTemplate && showSaveAsGenerate" class="flex items-center gap-2">
            <InputText
              v-model="newTemplateName"
              class="flex-1"
              size="small"
              placeholder="New template name…"
            />
            <Button
              label="Save"
              icon="pi pi-check"
              size="small"
              :disabled="!newTemplateName.trim()"
              :loading="savingTemplate"
              @click="saveTemplate('generate')"
            />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="generateDialogVisible = false" />
        <Button label="Generate" icon="pi pi-sparkles" :loading="generating" @click="generateSetlist" />
      </template>
    </Dialog>

    <ConfirmDialog />

    <!-- Refine with AI drawer -->
    <Sidebar
      v-model:visible="refineDrawerOpen"
      position="right"
      :modal="false"
      :show-close-icon="true"
      :style="{ width: 'min(420px, 100vw)' }"
      header="Refine Setlist"
      class="print:hidden"
    >
      <div class="flex flex-col h-full gap-3">
        <!-- Chat log -->
        <div ref="chatLog" class="flex-1 overflow-y-auto flex flex-col gap-3 pr-1">
          <div
            v-if="chatHistory.length === 0"
            class="text-sm text-gray-400 dark:text-gray-500 text-center mt-6"
          >
            Describe what you'd like to change.<br />
            <span class="text-xs">e.g. "Swap Into the Mystic for something more upbeat" or "Move the break to after song 8"</span>
          </div>

          <template v-for="(turn, i) in chatHistory" :key="i">
            <!-- User message -->
            <div v-if="turn.role === 'user'" class="flex justify-end">
              <div class="bg-blue-600 text-white text-sm rounded-2xl rounded-tr-sm px-3 py-2 max-w-[85%]">
                {{ turn.content }}
              </div>
            </div>

            <!-- AI summary + proposal -->
            <div v-else class="flex flex-col gap-2">
              <div class="bg-gray-100 dark:bg-slate-700 text-sm rounded-2xl rounded-tl-sm px-3 py-2 max-w-[85%] text-gray-800 dark:text-gray-200">
                {{ turn.content }}
              </div>
              <!-- Accept / dismiss for the most recent proposal only -->
              <div
                v-if="i === chatHistory.length - 1 && pendingSetlist"
                class="flex gap-2 ml-1"
              >
                <Button
                  label="Apply"
                  icon="pi pi-check"
                  size="small"
                  severity="success"
                  @click="applyProposal"
                />
                <Button
                  label="Dismiss"
                  icon="pi pi-times"
                  size="small"
                  severity="secondary"
                  outlined
                  @click="dismissProposal"
                />
              </div>
            </div>
          </template>

          <!-- Typing indicator -->
          <div v-if="refining" class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500">
            <i class="pi pi-spin pi-spinner" />
            AI is refining…
          </div>
        </div>

        <!-- Input -->
        <div class="flex flex-col gap-2 pt-2 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
          <!-- Saved prompt chips -->
          <div v-if="promptTemplates.length > 0" class="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
            <p class="w-full text-xs text-gray-400 dark:text-gray-500">Saved prompts — click to load</p>
            <button
              v-for="tpl in promptTemplates"
              :key="tpl.id"
              :class="[
                'inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border transition-colors',
                selectedRefineTemplate?.id === tpl.id
                  ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                  : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600'
              ]"
              :aria-label="tpl.name"
              @click="loadRefineTemplate(tpl)"
            >
              <i class="pi pi-bookmark text-xs" />
              {{ tpl.name }}
            </button>
          </div>

          <div class="flex gap-2">
            <Textarea
              ref="refineTextarea"
              v-model="refineMessage"
              class="flex-1"
              rows="2"
              auto-resize
              placeholder="What would you like to change?"
              :disabled="refining"
              @keydown.enter.exact.prevent="sendRefine"
            />
            <Button
              icon="pi pi-send"
              :loading="refining"
              :disabled="!refineMessage.trim()"
              @click="sendRefine"
            />
          </div>

          <!-- Loaded-from indicator -->
          <div v-if="selectedRefineTemplate" class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <i class="pi pi-bookmark text-xs" />
            Loaded from: <span class="font-medium">{{ selectedRefineTemplate.name }}</span>
            <button class="ml-1 hover:text-gray-700 dark:hover:text-gray-200" @click="clearRefineTemplate">clear</button>
          </div>

          <!-- Save actions -->
          <div v-if="refineMessage.trim()" class="flex flex-col gap-1.5">
            <!-- Loaded from template: Update or Save as new -->
            <div v-if="selectedRefineTemplate" class="flex items-center gap-2 flex-wrap">
              <Button
                :label="`Update &quot;${selectedRefineTemplate.name}&quot;`"
                icon="pi pi-bookmark"
                size="small"
                :loading="savingTemplate"
                @click="updateTemplate('refine')"
              />
              <Button
                label="Save as new…"
                size="small"
                severity="secondary"
                outlined
                @click="showSaveAsRefine = !showSaveAsRefine"
              />
            </div>
            <!-- Fresh text: Save prompt -->
            <div v-else class="flex items-center gap-2">
              <InputText
                v-model="newTemplateName"
                class="flex-1"
                size="small"
                placeholder="Save as template…"
              />
              <Button
                icon="pi pi-bookmark"
                size="small"
                severity="secondary"
                text
                :disabled="!newTemplateName.trim()"
                :loading="savingTemplate"
                v-tooltip.top="'Save prompt'"
                @click="saveTemplate('refine')"
              />
            </div>
            <!-- Save as new name field (only when loaded template + expanded) -->
            <div v-if="selectedRefineTemplate && showSaveAsRefine" class="flex items-center gap-2">
              <InputText
                v-model="newTemplateName"
                class="flex-1"
                size="small"
                placeholder="New template name…"
              />
              <Button
                icon="pi pi-check"
                size="small"
                :disabled="!newTemplateName.trim()"
                :loading="savingTemplate"
                v-tooltip.top="'Save as new'"
                @click="saveTemplate('refine')"
              />
            </div>
          </div>
        </div>
      </div>
    </Sidebar>

    <!-- Add / Edit song dialog -->
    <Dialog
      v-model:visible="entryDialogVisible"
      :header="editingIndex !== null ? 'Edit Entry' : 'Add Song'"
      :style="{ width: 'min(500px, 95vw)' }"
      modal
    >
      <div class="flex flex-col gap-4 pt-2">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Song from library
          </label>
          <Dropdown
            v-model="entryForm.song_id"
            :options="songOptions"
            option-label="label"
            option-value="id"
            placeholder="Select a song"
            show-clear
            filter
            class="w-full"
            @change="onSongSelect"
          />
        </div>

        <div class="text-center text-xs text-gray-400">— or enter custom song —</div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
          <InputText v-model="entryForm.custom_title" class="w-full" placeholder="Song title" :disabled="!!entryForm.song_id" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Artist</label>
          <InputText v-model="entryForm.custom_artist" class="w-full" placeholder="Artist" :disabled="!!entryForm.song_id" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes for this slot</label>
          <InputText v-model="entryForm.notes" class="w-full" placeholder="e.g. Play extended version" />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="entryDialogVisible = false" />
        <Button label="Add" @click="confirmEntry" :disabled="!entryForm.song_id && !entryForm.custom_title" />
      </template>
    </Dialog>
  </breeze-authenticated-layout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import { DateTime } from 'luxon';
import Sidebar from 'primevue/sidebar';

export default {
  components: { Link, draggable, Sidebar },

  props: {
    event: { type: Object, required: true },
    bandId: { type: Number, required: true },
    setlist: { type: Object, default: null },
    songs: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
  },

  data() {
    return {
      localSetlist: this.setlist ? JSON.parse(JSON.stringify(this.setlist)) : null,
      generating: false,
      saving: false,
      generateDialogVisible: false,
      aiContext: '',
      entryDialogVisible: false,
      editingIndex: null,
      entryForm: this.emptyEntryForm(),
      // Refine drawer
      refineDrawerOpen: false,
      refining: false,
      refineMessage: '',
      chatHistory: [],       // [{role:'user'|'assistant', content:string}]
      pendingSetlist: null,  // proposed setlist from AI, awaiting accept/dismiss
      previousSetlist: null, // snapshot before proposal, for dismiss
      // AI sources panel
      sourcesOpen: false,
      // Prompt templates
      promptTemplates: [],
      newTemplateName: '',
      savingTemplate: false,
      selectedGenerateTemplate: null,  // template loaded into the generate dialog
      showSaveAsGenerate: false,        // true when user expands "Save as new" in generate dialog
      selectedRefineTemplate: null,    // template loaded into the refine drawer
      showSaveAsRefine: false,          // true when user expands "Save as new" in refine drawer
    };
  },

  mounted() {
    this.loadTemplates();
  },

  computed: {
    songOptions() {
      return this.songs.map(s => ({
        ...s,
        label: s.artist ? `${s.title} – ${s.artist}` : s.title,
      }));
    },

    songCount() {
      return this.localSetlist?.songs?.filter(s => s.type !== 'break').length ?? 0;
    },

    totalDuration() {
      // Rough estimate: avg 3.5 min/song (excludes break rows)
      if (!this.songCount) return null;
      return Math.round(this.songCount * 3.5);
    },
  },

  methods: {
    emptyEntryForm() {
      return { type: 'song', song_id: null, custom_title: '', custom_artist: '', notes: '' };
    },

    imageTypeLabel(type) {
      return { MARKED_SETLIST: 'Marked setlist', PLAIN_SETLIST: 'Song list', TIMELINE: 'Timeline', OTHER: 'Other' }[type] ?? type;
    },

    imageTypeClass(type) {
      return {
        MARKED_SETLIST: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        PLAIN_SETLIST:  'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
        TIMELINE:       'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
        OTHER:          'bg-gray-100 dark:bg-slate-600 text-gray-600 dark:text-gray-300',
      }[type] ?? 'bg-gray-100 dark:bg-slate-600 text-gray-600 dark:text-gray-300';
    },

    // Returns the 1-based song number (ignoring break rows) for display
    songPosition(index) {
      let count = 0;
      for (let i = 0; i <= index; i++) {
        if (this.localSetlist.songs[i].type !== 'break') count++;
      }
      return count;
    },

    addBreak() {
      if (!this.localSetlist) {
        this.localSetlist = { id: null, status: 'draft', generated_at: null, songs: [] };
      }
      this.localSetlist.songs.push({
        id: Date.now(),
        type: 'break',
        song_id: null,
        title: null,
        artist: null,
        notes: null,
      });
    },

    formatDate(d) {
      if (!d) return '';
      return DateTime.fromISO(d).toFormat('MMM d, yyyy');
    },

    formatTime(t) {
      if (!t) return '';
      const dt = DateTime.fromFormat(t, 'HH:mm:ss');
      return dt.isValid ? dt.toFormat('h:mm a') : t;
    },

    formatRelative(iso) {
      if (!iso) return '';
      return DateTime.fromISO(iso).toRelative();
    },

    openGenerateDialog() {
      this.aiContext = '';
      this.selectedGenerateTemplate = null;
      this.showSaveAsGenerate = false;
      this.newTemplateName = '';
      this.generateDialogVisible = true;
    },

    async generateSetlist() {
      this.generating = true;
      this.generateDialogVisible = false;
      try {
        const { data } = await axios.post(route('setlists.generate', this.event.key), {
          context: this.aiContext || null,
        });
        this.localSetlist = data;
      } catch (err) {
        this.$toast?.add({
          severity: 'error',
          summary: 'Generation failed',
          detail: err.response?.data?.error ?? 'Could not generate setlist.',
          life: 6000,
        });
      } finally {
        this.generating = false;
      }
    },

    async saveSetlist() {
      if (!this.localSetlist) return;
      this.saving = true;
      try {
        const { data } = await axios.put(route('setlists.update', this.event.key), {
          songs: this.localSetlist.songs.map(s => ({
            type: s.type ?? 'song',
            song_id: s.song_id ?? null,
            custom_title: s.custom_title ?? null,
            custom_artist: s.custom_artist ?? null,
            notes: s.notes ?? null,
          })),
          status: this.localSetlist.status,
        });
        this.localSetlist = data;
        this.$toast?.add({ severity: 'success', summary: 'Saved', life: 2000 });
      } catch (err) {
        const detail = err.response?.data?.message
          ?? JSON.stringify(err.response?.data?.errors ?? err.response?.data ?? 'Save failed');
        this.$toast?.add({ severity: 'error', summary: 'Save failed', detail, life: 6000 });
      } finally {
        this.saving = false;
      }
    },

    async markReady() {
      if (!this.localSetlist) return;
      this.localSetlist.status = 'ready';
      await this.saveSetlist();
    },

    onDragEnd() {
      // Positions are implicit from array order; no extra work needed
    },

    openAddDialog() {
      this.editingIndex = null;
      this.entryForm = this.emptyEntryForm();
      this.entryDialogVisible = true;
    },

    editEntry(element, index) {
      this.editingIndex = index;
      this.entryForm = {
        song_id: element.song_id ?? null,
        custom_title: element.custom_title ?? '',
        custom_artist: element.custom_artist ?? '',
        notes: element.notes ?? '',
      };
      this.entryDialogVisible = true;
    },

    onSongSelect(e) {
      const song = this.songs.find(s => s.id === e.value);
      if (song) {
        this.entryForm.custom_title = '';
        this.entryForm.custom_artist = '';
      }
    },

    confirmEntry() {
      const song = this.songs.find(s => s.id === this.entryForm.song_id);
      const entry = {
        id: Date.now(), // temp client-side id for draggable key
        type: 'song',
        song_id: this.entryForm.song_id ?? null,
        custom_title: this.entryForm.custom_title || null,
        custom_artist: this.entryForm.custom_artist || null,
        title: song ? song.title : this.entryForm.custom_title,
        artist: song ? song.artist : this.entryForm.custom_artist,
        song_key: song?.song_key ?? null,
        genre: song?.genre ?? null,
        bpm: song?.bpm ?? null,
        energy: song?.energy ?? null,
        lead_singer: song?.lead_singer ?? null,
        notes: this.entryForm.notes || null,
      };

      if (!this.localSetlist) {
        this.localSetlist = { id: null, status: 'draft', generated_at: null, songs: [] };
      }

      if (this.editingIndex !== null) {
        this.localSetlist.songs.splice(this.editingIndex, 1, entry);
      } else {
        this.localSetlist.songs.push(entry);
      }

      this.entryDialogVisible = false;
    },

    async sendRefine() {
      const msg = this.refineMessage.trim();
      if (!msg || this.refining) return;

      // If there's a pending proposal not yet accepted, dismiss it first
      if (this.pendingSetlist) {
        this.dismissProposal();
      }

      this.chatHistory.push({ role: 'user', content: msg });
      this.refineMessage = '';
      this.selectedRefineTemplate = null;
      this.showSaveAsRefine = false;
      this.refining = true;
      this.$nextTick(() => this.scrollChatToBottom());

      try {
        // Build history excluding the message we just pushed (it goes as `message`)
        const history = this.chatHistory.slice(0, -1);

        const { data } = await axios.post(route('setlists.refine', this.event.key), {
          message: msg,
          history,
        });

        this.chatHistory.push({ role: 'assistant', content: data.summary });
        this.previousSetlist = JSON.parse(JSON.stringify(this.localSetlist));
        this.pendingSetlist  = data.setlist;

        // Preview the proposal in the main setlist immediately
        this.localSetlist = data.setlist;
      } catch (err) {
        this.chatHistory.push({
          role: 'assistant',
          content: err.response?.data?.error ?? 'Something went wrong. Please try again.',
        });
      } finally {
        this.refining = false;
        this.$nextTick(() => this.scrollChatToBottom());
      }
    },

    applyProposal() {
      this.pendingSetlist  = null;
      this.previousSetlist = null;
    },

    dismissProposal() {
      if (this.previousSetlist) {
        this.localSetlist = this.previousSetlist;
      }
      this.pendingSetlist  = null;
      this.previousSetlist = null;
    },

    scrollChatToBottom() {
      const el = this.$refs.chatLog;
      if (el) el.scrollTop = el.scrollHeight;
    },

    removeEntry(index) {
      this.localSetlist.songs.splice(index, 1);
    },

    confirmClear() {
      this.$confirm.require({
        message: 'Clear the entire setlist? This cannot be undone.',
        header: 'Clear Setlist',
        icon: 'pi pi-exclamation-triangle',
        acceptSeverity: 'danger',
        acceptLabel: 'Clear',
        rejectLabel: 'Cancel',
        accept: () => this.clearSetlist(),
      });
    },

    printSetlist() {
      window.print();
    },

    async clearSetlist() {
      this.saving = true;
      try {
        await axios.put(route('setlists.update', this.event.key), {
          songs: [],
          status: 'draft',
        });
        this.localSetlist = { id: this.localSetlist?.id, status: 'draft', generated_at: null, songs: [] };
        this.$toast?.add({ severity: 'info', summary: 'Setlist cleared', life: 2000 });
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Failed to clear setlist', life: 4000 });
      } finally {
        this.saving = false;
      }
    },

    async loadTemplates() {
      try {
        const { data } = await axios.get(route('setlists.prompt-templates.index', this.bandId));
        this.promptTemplates = data;
      } catch {
        // Non-critical; silently ignore
      }
    },

    loadGenerateTemplate(tpl) {
      this.aiContext = tpl.prompt;
      this.selectedGenerateTemplate = tpl;
      this.showSaveAsGenerate = false;
      this.newTemplateName = '';
      this.$nextTick(() => this.$refs.generateTextarea?.$el?.focus());
    },

    clearGenerateTemplate() {
      this.selectedGenerateTemplate = null;
      this.showSaveAsGenerate = false;
      this.newTemplateName = '';
    },

    loadRefineTemplate(tpl) {
      this.refineMessage = tpl.prompt;
      this.selectedRefineTemplate = tpl;
      this.showSaveAsRefine = false;
      this.newTemplateName = '';
      this.$nextTick(() => this.$refs.refineTextarea?.$el?.focus());
    },

    clearRefineTemplate() {
      this.selectedRefineTemplate = null;
      this.showSaveAsRefine = false;
      this.newTemplateName = '';
    },

    async updateTemplate(context) {
      const tpl = context === 'generate' ? this.selectedGenerateTemplate : this.selectedRefineTemplate;
      const prompt = context === 'generate' ? this.aiContext.trim() : this.refineMessage.trim();
      if (!tpl || !prompt) return;

      this.savingTemplate = true;
      try {
        const { data } = await axios.patch(
          route('setlists.prompt-templates.update', { band: this.bandId, template: tpl.id }),
          { prompt }
        );
        const idx = this.promptTemplates.findIndex(t => t.id === tpl.id);
        if (idx !== -1) this.promptTemplates.splice(idx, 1, data);
        if (context === 'generate') this.selectedGenerateTemplate = data;
        else this.selectedRefineTemplate = data;
        this.$toast?.add({ severity: 'success', summary: 'Prompt updated', life: 2000 });
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not update prompt', life: 4000 });
      } finally {
        this.savingTemplate = false;
      }
    },

    async saveTemplate(context) {
      const prompt = context === 'generate' ? this.aiContext.trim() : this.refineMessage.trim();
      if (!prompt || !this.newTemplateName.trim()) return;

      this.savingTemplate = true;
      try {
        const { data } = await axios.post(route('setlists.prompt-templates.store', this.bandId), {
          name: this.newTemplateName.trim(),
          prompt,
        });
        this.promptTemplates.push(data);
        this.promptTemplates.sort((a, b) => a.name.localeCompare(b.name));
        this.newTemplateName = '';
        if (context === 'generate') {
          this.selectedGenerateTemplate = data;
          this.showSaveAsGenerate = false;
        } else {
          this.selectedRefineTemplate = data;
          this.showSaveAsRefine = false;
        }
        this.$toast?.add({ severity: 'success', summary: 'Prompt saved', life: 2000 });
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not save prompt', life: 4000 });
      } finally {
        this.savingTemplate = false;
      }
    },

    async deleteTemplate(tpl) {
      try {
        await axios.delete(route('setlists.prompt-templates.destroy', { band: this.bandId, template: tpl.id }));
        this.promptTemplates = this.promptTemplates.filter(t => t.id !== tpl.id);
        if (this.selectedGenerateTemplate?.id === tpl.id) {
          this.selectedGenerateTemplate = null;
          this.showSaveAsGenerate = false;
          this.newTemplateName = '';
        }
        if (this.selectedRefineTemplate?.id === tpl.id) {
          this.selectedRefineTemplate = null;
          this.showSaveAsRefine = false;
          this.newTemplateName = '';
        }
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not delete prompt', life: 4000 });
      }
    },
  },
};
</script>

<style>
@media print {
  nav,
  header,
  aside {
    display: none !important;
  }

  /* Remove the pt-16 layout wrapper padding */
  .pt-16 {
    padding-top: 0 !important;
  }

  /* Hide everything inside app except the print area */
  body * {
    visibility: hidden;
  }

  #setlist-print-area,
  #setlist-print-area * {
    visibility: visible;
  }

  #setlist-print-area {
    background: white;
    color: black;
    font-size: 10pt;
  }

  /* Remove shadows/rounded corners */
  #setlist-print-area .rounded-lg,
  #setlist-print-area .shadow {
    border-radius: 0 !important;
    box-shadow: none !important;
  }

  /* Hide drag handles and action buttons */
  #setlist-print-area .drag-handle,
  #setlist-print-area button {
    display: none !important;
  }

  /* Force light text colors for print */
  #setlist-print-area [class*="text-gray"] {
    color: #333 !important;
  }

  /* Compact song rows */
  #setlist-print-area .border-b {
    page-break-inside: avoid;
    padding-top: 4px !important;
    padding-bottom: 4px !important;
  }

  /* Tighten up the print header */
  #setlist-print-area .mb-3 {
    margin-bottom: 6px !important;
  }
}
</style>
